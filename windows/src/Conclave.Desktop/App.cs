using Microsoft.UI.Xaml;
using Microsoft.UI.Xaml.Controls;
using Microsoft.UI.Xaml.Markup;
using Microsoft.UI.Xaml.XamlTypeInfo;
using Microsoft.Windows.AppLifecycle;
using Microsoft.Windows.AppNotifications;

namespace Conclave.Desktop;

/// <summary>
/// The application: one window, the notification area icon and the Windows notifications.
/// The interface is built in code, so it provides the XAML type information itself.
/// </summary>
public sealed class App : Application, IXamlMetadataProvider
{
    private readonly XamlControlsXamlMetaDataProvider metadata = new();
    private readonly AppInstance instance;
    private MainWindow? window;

    public App(AppInstance instance)
    {
        this.instance = instance;
        UnhandledException += (_, e) => Log.Error("Unhandled exception", e.Exception);
    }

    protected override void OnLaunched(LaunchActivatedEventArgs args)
    {
        Resources.MergedDictionaries.Add(new XamlControlsResources());

        window = new MainWindow();
        Notifications.Start(window);

        // Launched again, or by a click on a notification while running
        instance.Activated += (_, activation) =>
            window.DispatcherQueue.TryEnqueue(() => window.HandleActivation(activation));

        window.HandleActivation(instance.GetActivatedEventArgs(), launching: true);
    }

    public IXamlType GetXamlType(Type type) => metadata.GetXamlType(type);

    public IXamlType GetXamlType(string fullName) => metadata.GetXamlType(fullName);

    public XmlnsDefinition[] GetXmlnsDefinitions() => metadata.GetXmlnsDefinitions();
}
