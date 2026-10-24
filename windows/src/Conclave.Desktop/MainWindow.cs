using System.Text.Json;
using Conclave.Desktop.Core;
using Microsoft.UI.Windowing;
using Microsoft.UI.Xaml;
using Microsoft.UI.Xaml.Controls;
using Microsoft.UI.Xaml.Input;
using Microsoft.UI.Xaml.Media;
using Microsoft.UI.Xaml.Media.Imaging;
using Microsoft.Web.WebView2.Core;
using Microsoft.Windows.AppLifecycle;
using Microsoft.Windows.AppNotifications;
using Windows.Graphics;
using Windows.System;

namespace Conclave.Desktop;

/// <summary>
/// The window: Windows 11 title bar, then the clan's Conclave in WebView2. Before that, the
/// address of the server is asked once; when it does not answer, a panel says so.
/// </summary>
public sealed class MainWindow : Window
{
    private static readonly HttpClient Http = new();
    private static readonly SizeInt32 DefaultSize = new(1280, 860);

    private readonly SettingsStore store = new(AppPaths.Settings);
    private readonly ServerClient client = new(Http);
    private readonly IntPtr handle;
    private DesktopSettings settings;

    private readonly TitleBar titleBar = new();
    private readonly InfoBar updateBar = new() { Severity = InfoBarSeverity.Informational, IsOpen = false };
    private readonly WebView2 webView = new() { Visibility = Visibility.Collapsed };
    private readonly ProgressRing busyRing = new() { IsActive = false, Width = 48, Height = 48 };

    private readonly Grid setupPanel;
    private readonly TextBox addressBox = new() { PlaceholderText = "http://192.168.1.10:8080" };
    private readonly InfoBar setupError = new() { Severity = InfoBarSeverity.Error, IsClosable = false, IsOpen = false };
    private readonly Button connectButton = new() { Content = "Se connecter" };

    private readonly Grid offlinePanel;
    private readonly TextBlock offlineText = new() { TextWrapping = TextWrapping.Wrap, TextAlignment = TextAlignment.Center };

    private TrayIcon? tray;
    private Uri? server;
    private bool coreReady;
    private bool pageLoaded;
    private bool quitting;
    private string? pendingLink;
    private bool hiddenStart;

    public MainWindow()
    {
        handle = WinRT.Interop.WindowNative.GetWindowHandle(this);
        settings = store.Load();

        Title = "Conclave";
        SystemBackdrop = new MicaBackdrop();
        ExtendsContentIntoTitleBar = true;
        AppWindow.TitleBar.PreferredHeightOption = TitleBarHeightOption.Tall;
        AppWindow.SetIcon(AppPaths.Asset("conclave.ico"));

        titleBar.Title = "Conclave";
        titleBar.IconSource = new ImageIconSource { ImageSource = Bitmap("conclave-256.png") };
        titleBar.IsBackButtonVisible = true;
        titleBar.IsBackButtonEnabled = false;
        titleBar.BackRequested += (_, _) =>
        {
            if (coreReady && webView.CoreWebView2.CanGoBack)
            {
                webView.CoreWebView2.GoBack();
            }
        };
        var settingsButton = new Button
        {
            Content = new FontIcon { Glyph = "", FontSize = 16 },
            Style = (Style)Application.Current.Resources["SubtleButtonStyle"],
            Margin = new Thickness(0, 0, 8, 0),
        };
        ToolTipService.SetToolTip(settingsButton, "Réglages");
        AutomationName(settingsButton, "Réglages");
        settingsButton.Click += async (_, _) => await OpenSettingsAsync();
        titleBar.RightHeader = settingsButton;
        SetTitleBar(titleBar);

        setupPanel = BuildSetupPanel();
        offlinePanel = BuildOfflinePanel();

        var content = new Grid();
        content.Children.Add(webView);
        content.Children.Add(setupPanel);
        content.Children.Add(offlinePanel);
        var ring = new Grid { HorizontalAlignment = HorizontalAlignment.Center, VerticalAlignment = VerticalAlignment.Center, IsHitTestVisible = false };
        ring.Children.Add(busyRing);
        content.Children.Add(ring);

        var root = new Grid();
        root.RowDefinitions.Add(new RowDefinition { Height = GridLength.Auto });
        root.RowDefinitions.Add(new RowDefinition { Height = GridLength.Auto });
        root.RowDefinitions.Add(new RowDefinition { Height = new GridLength(1, GridUnitType.Star) });
        Grid.SetRow(updateBar, 1);
        Grid.SetRow(content, 2);
        root.Children.Add(titleBar);
        root.Children.Add(updateBar);
        root.Children.Add(content);
        Content = root;

        RestorePlacement();
        AppWindow.Closing += OnClosing;

        tray = new TrayIcon(handle, AppPaths.Asset("conclave.ico"));
        tray.OpenRequested += () => DispatcherQueue.TryEnqueue(() => ShowPage(null));
        tray.QuitRequested += () => DispatcherQueue.TryEnqueue(Quit);
    }

    /// <summary>Launch, launch again, or a click on a notification that started Conclave.</summary>
    public void HandleActivation(AppActivationArguments activation, bool launching = false)
    {
        var link = activation.Kind == ExtendedActivationKind.AppNotification && activation.Data is AppNotificationActivatedEventArgs notification
            ? Notifications.LinkOf(notification.Arguments)
            : null;

        if (!launching)
        {
            ShowPage(link);
            return;
        }

        Activate();
        // Started with Windows: only in the notification area, the page loads behind
        hiddenStart = Program.StartMinimized && link is null && settings.ServerUrl is not null;
        if (hiddenStart)
        {
            AppWindow.Hide();
        }
        else if (settings.Window?.Maximized == true && AppWindow.Presenter is OverlappedPresenter presenter)
        {
            presenter.Maximize();
        }

        if (ServerAddress.TryParse(settings.ServerUrl, out var address, out _))
        {
            _ = ConnectAsync(address, link, fromSetup: false);
        }
        else
        {
            ShowSetup();
        }
    }

    /// <summary>Brings the window forward, on a page of the server ("/evenements/4") if given.</summary>
    public void ShowPage(string? link)
    {
        AppWindow.Show();
        if (AppWindow.Presenter is OverlappedPresenter { State: OverlappedPresenterState.Minimized } presenter)
        {
            presenter.Restore();
        }

        Activate();
        NativeMethods.SetForegroundWindow(handle);

        if (server is null || NavigationPolicy.PageOf(server, link) is not { } page)
        {
            return;
        }

        if (pageLoaded)
        {
            // The page's router changes page without reloading Conclave
            webView.CoreWebView2.PostWebMessageAsJson(JsonSerializer.Serialize(new { type = "navigate", link }));
        }
        else
        {
            pendingLink = link;
            if (coreReady)
            {
                webView.CoreWebView2.Navigate(page.AbsoluteUri);
            }
        }
    }

    private async Task ConnectAsync(Uri address, string? link, bool fromSetup)
    {
        SetBusy(true);
        setupError.IsOpen = false;
        var check = await client.CheckAsync(address);
        SetBusy(false);
        if (!check.Ok)
        {
            Log.Info($"Server {address} unavailable: {check.Error}");
            if (fromSetup)
            {
                setupError.Message = check.Error;
                setupError.IsOpen = true;
            }
            else
            {
                ShowOffline(check.Error);
            }

            return;
        }

        server = address;
        settings = settings with { ServerUrl = address.AbsoluteUri };
        store.Save(settings);
        titleBar.Subtitle = ServerAddress.Label(address);
        setupPanel.Visibility = Visibility.Collapsed;
        offlinePanel.Visibility = Visibility.Collapsed;
        webView.Visibility = Visibility.Visible;

        if (!await EnsureCoreAsync())
        {
            return;
        }

        pageLoaded = false;
        pendingLink = null;
        webView.CoreWebView2.Navigate((NavigationPolicy.PageOf(address, link) ?? address).AbsoluteUri);
        ShowUpdate(check.Release);
    }

    private async Task<bool> EnsureCoreAsync()
    {
        if (coreReady)
        {
            return true;
        }

        try
        {
            await webView.EnsureCoreWebView2Async();
        }
        catch (Exception e)
        {
            Log.Error("WebView2 unavailable", e);
            ShowOffline("Le composant WebView2 de Windows est introuvable. Installez « Microsoft Edge WebView2 Runtime » depuis le site de Microsoft, puis relancez Conclave.");
            return false;
        }

        var core = webView.CoreWebView2;
        core.Settings.UserAgent += $" ConclaveDesktop/{AppPaths.Version}";
        core.Settings.IsStatusBarEnabled = false;
        core.Settings.IsGeneralAutofillEnabled = true;
        core.Settings.IsPasswordAutosaveEnabled = true;
#if !DEBUG
        core.Settings.AreDevToolsEnabled = false;
#endif
        // Tells the page it runs in the application: it then uses its notifications and badge
        await core.AddScriptToExecuteOnDocumentCreatedAsync(
            $"window.conclaveDesktop = Object.freeze({{ platform: 'windows', version: '{AppPaths.Version}' }});");

        core.NavigationStarting += (_, args) =>
        {
            if (server is not null && Uri.TryCreate(args.Uri, UriKind.Absolute, out var target) && !NavigationPolicy.StaysInside(server, target))
            {
                args.Cancel = true;
                OpenInBrowser(target);
            }
        };
        core.NewWindowRequested += (_, args) =>
        {
            args.Handled = true;
            if (!Uri.TryCreate(args.Uri, UriKind.Absolute, out var target))
            {
                return;
            }

            if (server is not null && NavigationPolicy.StaysInside(server, target))
            {
                core.Navigate(target.AbsoluteUri);
            }
            else
            {
                OpenInBrowser(target);
            }
        };
        core.NavigationCompleted += OnNavigationCompleted;
        core.HistoryChanged += (_, _) => titleBar.IsBackButtonEnabled = core.CanGoBack;
        core.WebMessageReceived += OnWebMessage;
        core.PermissionRequested += (_, args) =>
        {
            // The page's notifications go through the application (Windows notifications)
            if (args.PermissionKind == CoreWebView2PermissionKind.Notifications)
            {
                args.State = CoreWebView2PermissionState.Deny;
            }
        };
        core.ProcessFailed += (_, args) =>
        {
            Log.Error($"WebView2 process failed: {args.ProcessFailedKind}");
            if (args.ProcessFailedKind == CoreWebView2ProcessFailedKind.RenderProcessExited)
            {
                core.Reload();
            }
        };

        coreReady = true;
        return true;
    }

    private void OnNavigationCompleted(CoreWebView2 core, CoreWebView2NavigationCompletedEventArgs args)
    {
        if (args.IsSuccess)
        {
            pageLoaded = true;
            offlinePanel.Visibility = Visibility.Collapsed;
            if (pendingLink is { } link)
            {
                pendingLink = null;
                core.PostWebMessageAsJson(JsonSerializer.Serialize(new { type = "navigate", link }));
            }

            return;
        }

        switch (args.WebErrorStatus)
        {
            case CoreWebView2WebErrorStatus.CannotConnect:
            case CoreWebView2WebErrorStatus.ConnectionAborted:
            case CoreWebView2WebErrorStatus.ConnectionReset:
            case CoreWebView2WebErrorStatus.Disconnected:
            case CoreWebView2WebErrorStatus.HostNameNotResolved:
            case CoreWebView2WebErrorStatus.ServerUnreachable:
            case CoreWebView2WebErrorStatus.Timeout:
                pageLoaded = false;
                ShowOffline("Le serveur de Conclave ne répond plus. Vérifiez votre connexion, ou que le serveur est démarré.");
                break;
        }
    }

    private void OnWebMessage(CoreWebView2 core, CoreWebView2WebMessageReceivedEventArgs args)
    {
        // Only the pages of the server talk to the application
        if (server is null || !Uri.TryCreate(args.Source, UriKind.Absolute, out var source) || !NavigationPolicy.StaysInside(server, source))
        {
            return;
        }

        string? json;
        try
        {
            json = args.TryGetWebMessageAsString();
        }
        catch (ArgumentException)
        {
            return;
        }

        switch (BridgeMessage.Parse(json))
        {
            case NotificationMessage notification:
                Notifications.Show(notification);
                break;
            case BadgeMessage badge:
                Title = badge.Count > 0 ? $"({badge.Count}) Conclave" : "Conclave";
                Notifications.Badge(badge.Count);
                break;
        }
    }

    private void ShowSetup()
    {
        webView.Visibility = Visibility.Collapsed;
        offlinePanel.Visibility = Visibility.Collapsed;
        setupPanel.Visibility = Visibility.Visible;
        addressBox.Text = settings.ServerUrl ?? string.Empty;
        addressBox.Focus(FocusState.Programmatic);
    }

    private void ShowOffline(string? message)
    {
        offlineText.Text = message ?? "Le serveur de Conclave ne répond pas.";
        setupPanel.Visibility = Visibility.Collapsed;
        offlinePanel.Visibility = Visibility.Visible;
        // Started hidden: say it, the member did not see the window
        if (hiddenStart)
        {
            hiddenStart = false;
            Log.Info("Hidden start: server unavailable");
        }
    }

    private void ShowUpdate(DesktopRelease? release)
    {
        if (release is null || !AppVersion.IsNewer(release.Version, AppPaths.Version))
        {
            updateBar.IsOpen = false;
            return;
        }

        var download = new Button { Content = "Télécharger" };
        download.Click += (_, _) => OpenInBrowser(release.DownloadUrl);
        updateBar.Title = "Nouvelle version";
        updateBar.Message = $"Conclave {release.Version} pour Windows est disponible (vous avez la {AppPaths.Version}).";
        updateBar.ActionButton = download;
        updateBar.IsOpen = true;
    }

    private void SetBusy(bool busy)
    {
        busyRing.IsActive = busy;
        connectButton.IsEnabled = !busy;
    }

    private async Task OpenSettingsAsync()
    {
        var dialog = new SettingsDialog(settings, client) { XamlRoot = Content.XamlRoot };
        if (await dialog.ShowAsync() != ContentDialogResult.Primary || dialog.Result is not { } chosen)
        {
            return;
        }

        if (chosen.StartWithWindows != settings.StartWithWindows)
        {
            StartupRegistration.Set(chosen.StartWithWindows);
        }

        var serverChanged = chosen.ServerUrl != settings.ServerUrl;
        settings = chosen;
        store.Save(settings);
        if (serverChanged && ServerAddress.TryParse(chosen.ServerUrl, out var address, out _))
        {
            await ConnectAsync(address, null, fromSetup: false);
        }
    }

    private void OnClosing(AppWindow sender, AppWindowClosingEventArgs args)
    {
        SavePlacement();
        if (!quitting && settings.CloseToTray && tray is not null)
        {
            // Conclave goes on in the notification area: notifications keep coming
            args.Cancel = true;
            AppWindow.Hide();
            if (!settings.TrayTipShown)
            {
                settings = settings with { TrayTipShown = true };
                store.Save(settings);
                Notifications.Show(new NotificationMessage(
                    "Conclave reste ouvert",
                    "Il continue dans la zone de notification pour vous prévenir. Pour le quitter : clic droit sur son icône.",
                    "tray-tip",
                    null));
            }

            return;
        }

        tray?.Dispose();
        tray = null;
    }

    private void Quit()
    {
        quitting = true;
        Close();
        Application.Current.Exit();
    }

    private void RestorePlacement()
    {
        if (settings.Window is { } placement)
        {
            var rect = new RectInt32(placement.X, placement.Y, placement.Width, placement.Height);
            // Only on a screen that still exists
            if (DisplayArea.GetFromRect(rect, DisplayAreaFallback.None) is not null)
            {
                AppWindow.MoveAndResize(rect);
                return;
            }
        }

        var area = DisplayArea.GetFromWindowId(AppWindow.Id, DisplayAreaFallback.Primary).WorkArea;
        var width = Math.Min(DefaultSize.Width, area.Width);
        var height = Math.Min(DefaultSize.Height, area.Height);
        AppWindow.MoveAndResize(new RectInt32(area.X + (area.Width - width) / 2, area.Y + (area.Height - height) / 2, width, height));
    }

    private void SavePlacement()
    {
        if (AppWindow.Presenter is not OverlappedPresenter presenter || presenter.State == OverlappedPresenterState.Minimized || !AppWindow.IsVisible)
        {
            return;
        }

        var maximized = presenter.State == OverlappedPresenterState.Maximized;
        // Maximized: keep the normal size and position for the next restore
        var previous = settings.Window;
        var placement = maximized && previous is not null
            ? previous with { Maximized = true }
            : new WindowPlacement(AppWindow.Position.X, AppWindow.Position.Y, AppWindow.Size.Width, AppWindow.Size.Height, maximized);
        settings = settings with { Window = placement };
        store.Save(settings);
    }

    private Grid BuildSetupPanel()
    {
        var title = new TextBlock { Text = "Bienvenue sur Conclave", Style = (Style)Application.Current.Resources["TitleTextBlockStyle"], HorizontalAlignment = HorizontalAlignment.Center };
        var intro = new TextBlock
        {
            Text = "Indiquez l'adresse du serveur Conclave de votre clan : celle que vous tapez dans votre navigateur. Un dirigeant du clan peut vous la donner.",
            TextWrapping = TextWrapping.Wrap,
            TextAlignment = TextAlignment.Center,
            Foreground = (Brush)Application.Current.Resources["TextFillColorSecondaryBrush"],
        };
        addressBox.Header = "Adresse du serveur";
        AutomationName(addressBox, "Adresse du serveur");
        addressBox.KeyDown += async (_, e) =>
        {
            if (e.Key == VirtualKey.Enter)
            {
                e.Handled = true;
                await ConnectFromSetupAsync();
            }
        };
        connectButton.Style = (Style)Application.Current.Resources["AccentButtonStyle"];
        connectButton.HorizontalAlignment = HorizontalAlignment.Stretch;
        connectButton.Click += async (_, _) => await ConnectFromSetupAsync();

        var stack = new StackPanel { Spacing = 16, MaxWidth = 440, Padding = new Thickness(24) };
        stack.Children.Add(new Image { Source = Bitmap("conclave-256.png"), Width = 96, Height = 96 });
        stack.Children.Add(title);
        stack.Children.Add(intro);
        stack.Children.Add(addressBox);
        stack.Children.Add(setupError);
        stack.Children.Add(connectButton);
        return Centered(stack);
    }

    private async Task ConnectFromSetupAsync()
    {
        if (!ServerAddress.TryParse(addressBox.Text, out var address, out var error))
        {
            setupError.Message = error;
            setupError.IsOpen = true;
            return;
        }

        await ConnectAsync(address, null, fromSetup: true);
    }

    private Grid BuildOfflinePanel()
    {
        var retry = new Button { Content = "Réessayer", Style = (Style)Application.Current.Resources["AccentButtonStyle"] };
        retry.Click += async (_, _) =>
        {
            if (ServerAddress.TryParse(settings.ServerUrl, out var address, out _))
            {
                await ConnectAsync(address, null, fromSetup: false);
            }
        };
        var change = new Button { Content = "Changer de serveur" };
        change.Click += (_, _) => ShowSetup();

        var buttons = new StackPanel { Orientation = Orientation.Horizontal, Spacing = 8, HorizontalAlignment = HorizontalAlignment.Center };
        buttons.Children.Add(retry);
        buttons.Children.Add(change);

        var stack = new StackPanel { Spacing = 16, MaxWidth = 460, Padding = new Thickness(24) };
        stack.Children.Add(new FontIcon { Glyph = "", FontSize = 48 });
        stack.Children.Add(new TextBlock { Text = "Impossible de joindre le serveur", Style = (Style)Application.Current.Resources["SubtitleTextBlockStyle"], HorizontalAlignment = HorizontalAlignment.Center });
        stack.Children.Add(offlineText);
        stack.Children.Add(buttons);
        var panel = Centered(stack);
        panel.Visibility = Visibility.Collapsed;
        return panel;
    }

    private static Grid Centered(UIElement child)
    {
        var grid = new Grid { HorizontalAlignment = HorizontalAlignment.Center, VerticalAlignment = VerticalAlignment.Center };
        grid.Children.Add(child);
        return grid;
    }

    private static BitmapImage Bitmap(string asset) => new(new Uri(AppPaths.Asset(asset)));

    private static void AutomationName(DependencyObject element, string name) =>
        Microsoft.UI.Xaml.Automation.AutomationProperties.SetName(element, name);

    private static void OpenInBrowser(Uri target)
    {
        if (target.Scheme is "http" or "https" or "mailto")
        {
            _ = Launcher.LaunchUriAsync(target);
        }
    }
}
