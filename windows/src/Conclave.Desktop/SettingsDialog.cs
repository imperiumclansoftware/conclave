using Conclave.Desktop.Core;
using Microsoft.UI.Xaml;
using Microsoft.UI.Xaml.Controls;
using Microsoft.UI.Xaml.Media;

namespace Conclave.Desktop;

/// <summary>Settings of the application: server, start with Windows, notification area.</summary>
internal sealed class SettingsDialog : ContentDialog
{
    private readonly DesktopSettings current;
    private readonly ServerClient client;
    private readonly TextBox address = new() { Header = "Adresse du serveur" };
    private readonly InfoBar error = new() { Severity = InfoBarSeverity.Error, IsClosable = false, IsOpen = false };
    private readonly ToggleSwitch startWithWindows = new() { Header = "Lancer Conclave au démarrage de Windows" };
    private readonly ToggleSwitch closeToTray = new() { Header = "À la fermeture, garder Conclave dans la zone de notification" };

    /// <summary>The settings chosen, once saved</summary>
    public DesktopSettings? Result { get; private set; }

    public SettingsDialog(DesktopSettings current, ServerClient client)
    {
        this.current = current;
        this.client = client;

        Title = "Réglages";
        PrimaryButtonText = "Enregistrer";
        CloseButtonText = "Annuler";
        DefaultButton = ContentDialogButton.Primary;

        address.Text = current.ServerUrl ?? string.Empty;
        startWithWindows.IsOn = current.StartWithWindows;
        startWithWindows.OnContent = startWithWindows.OffContent = null;
        closeToTray.IsOn = current.CloseToTray;
        closeToTray.OnContent = closeToTray.OffContent = null;

        var secondary = (Brush)Application.Current.Resources["TextFillColorSecondaryBrush"];
        var panel = new StackPanel { Spacing = 12, MinWidth = 380 };
        panel.Children.Add(address);
        panel.Children.Add(new TextBlock { Text = "Changer de serveur vous connecte au Conclave d'un autre clan.", Foreground = secondary, TextWrapping = TextWrapping.Wrap });
        panel.Children.Add(error);
        panel.Children.Add(startWithWindows);
        panel.Children.Add(closeToTray);
        panel.Children.Add(new TextBlock
        {
            Text = "Dans la zone de notification, Conclave continue de vous prévenir des messages et des événements.",
            Foreground = secondary,
            TextWrapping = TextWrapping.Wrap,
        });
        panel.Children.Add(new TextBlock { Text = $"Conclave pour Windows {AppPaths.Version}", Foreground = secondary, Margin = new Thickness(0, 8, 0, 0) });
        Content = panel;

        PrimaryButtonClick += OnSave;
    }

    private async void OnSave(ContentDialog sender, ContentDialogButtonClickEventArgs args)
    {
        // The dialog stays open while the new server is checked, and if it is not valid
        var deferral = args.GetDeferral();
        try
        {
            if (!ServerAddress.TryParse(address.Text, out var server, out var message))
            {
                Fail(args, message);
                return;
            }

            if (server.AbsoluteUri != current.ServerUrl)
            {
                IsPrimaryButtonEnabled = false;
                var check = await client.CheckAsync(server);
                IsPrimaryButtonEnabled = true;
                if (!check.Ok)
                {
                    Fail(args, check.Error);
                    return;
                }
            }

            Result = current with
            {
                ServerUrl = server.AbsoluteUri,
                StartWithWindows = startWithWindows.IsOn,
                CloseToTray = closeToTray.IsOn,
            };
        }
        finally
        {
            deferral.Complete();
        }
    }

    private void Fail(ContentDialogButtonClickEventArgs args, string? message)
    {
        args.Cancel = true;
        error.Message = message;
        error.IsOpen = true;
    }
}
