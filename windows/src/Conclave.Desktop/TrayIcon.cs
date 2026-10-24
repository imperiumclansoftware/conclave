namespace Conclave.Desktop;

/// <summary>
/// Icon in the notification area: Conclave keeps running there when its window is closed, so
/// that the notifications go on. A click opens the window; a right click offers to quit.
/// </summary>
internal sealed class TrayIcon : IDisposable
{
    private const uint CallbackMessage = NativeMethods.WM_APP + 1;
    private const uint OpenCommand = 1;
    private const uint QuitCommand = 2;
    private static readonly uint TaskbarCreated = NativeMethods.RegisterWindowMessageW("TaskbarCreated");

    private readonly IntPtr window;
    private readonly IntPtr icon;
    // Kept as a field: the delegate must live as long as the subclass
    private readonly NativeMethods.SubclassProc subclass;
    private bool added;

    public event Action? OpenRequested;

    public event Action? QuitRequested;

    public TrayIcon(IntPtr window, string iconPath)
    {
        this.window = window;
        icon = NativeMethods.LoadImageW(IntPtr.Zero, iconPath, NativeMethods.IMAGE_ICON,
            NativeMethods.GetSystemMetrics(NativeMethods.SM_CXSMICON), NativeMethods.GetSystemMetrics(NativeMethods.SM_CYSMICON),
            NativeMethods.LR_LOADFROMFILE);
        subclass = OnMessage;
        NativeMethods.SetWindowSubclass(window, subclass, 1, 0);
        Add();
    }

    private void Add()
    {
        var data = Data();
        data.uFlags = NativeMethods.NIF_MESSAGE | NativeMethods.NIF_ICON | NativeMethods.NIF_TIP | NativeMethods.NIF_SHOWTIP;
        added = NativeMethods.Shell_NotifyIconW(NativeMethods.NIM_ADD, ref data);
        data.uVersion = NativeMethods.NOTIFYICON_VERSION_4;
        NativeMethods.Shell_NotifyIconW(NativeMethods.NIM_SETVERSION, ref data);
    }

    private NativeMethods.NOTIFYICONDATAW Data() => new()
    {
        cbSize = (uint)System.Runtime.InteropServices.Marshal.SizeOf<NativeMethods.NOTIFYICONDATAW>(),
        hWnd = window,
        uID = 1,
        uCallbackMessage = CallbackMessage,
        hIcon = icon,
        szTip = "Conclave",
        szInfo = string.Empty,
        szInfoTitle = string.Empty,
    };

    private IntPtr OnMessage(IntPtr hWnd, uint msg, IntPtr wParam, IntPtr lParam, UIntPtr id, UIntPtr refData)
    {
        if (msg == CallbackMessage)
        {
            switch ((uint)NativeMethods.LowWord(lParam))
            {
                case NativeMethods.NIN_SELECT or NativeMethods.NIN_KEYSELECT or NativeMethods.WM_LBUTTONDBLCLK:
                    OpenRequested?.Invoke();
                    break;
                case NativeMethods.WM_CONTEXTMENU:
                    ShowMenu(NativeMethods.LowWord(wParam), NativeMethods.HighWord(wParam));
                    break;
            }

            return IntPtr.Zero;
        }

        // Explorer restarted: the icon must be added again
        if (msg == TaskbarCreated)
        {
            Add();
        }

        return NativeMethods.DefSubclassProc(hWnd, msg, wParam, lParam);
    }

    private void ShowMenu(int x, int y)
    {
        var menu = NativeMethods.CreatePopupMenu();
        NativeMethods.AppendMenuW(menu, NativeMethods.MF_STRING, OpenCommand, "Ouvrir Conclave");
        NativeMethods.AppendMenuW(menu, NativeMethods.MF_SEPARATOR, 0, null);
        NativeMethods.AppendMenuW(menu, NativeMethods.MF_STRING, QuitCommand, "Quitter");
        // Without this, the menu would not close when clicking elsewhere
        NativeMethods.SetForegroundWindow(window);
        var command = (uint)NativeMethods.TrackPopupMenuEx(menu,
            NativeMethods.TPM_RETURNCMD | NativeMethods.TPM_RIGHTBUTTON | NativeMethods.TPM_BOTTOMALIGN, x, y, window, IntPtr.Zero);
        NativeMethods.PostMessageW(window, NativeMethods.WM_NULL, IntPtr.Zero, IntPtr.Zero);
        NativeMethods.DestroyMenu(menu);

        switch (command)
        {
            case OpenCommand:
                OpenRequested?.Invoke();
                break;
            case QuitCommand:
                QuitRequested?.Invoke();
                break;
        }
    }

    public void Dispose()
    {
        if (added)
        {
            var data = Data();
            NativeMethods.Shell_NotifyIconW(NativeMethods.NIM_DELETE, ref data);
            added = false;
        }

        NativeMethods.RemoveWindowSubclass(window, subclass, 1);
        if (icon != IntPtr.Zero)
        {
            NativeMethods.DestroyIcon(icon);
        }
    }
}
