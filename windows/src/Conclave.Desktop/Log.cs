namespace Conclave.Desktop;

/// <summary>A small log in the member's data, to understand a problem they report.</summary>
internal static class Log
{
    private const long MaxBytes = 512 * 1024;
    private static readonly string FilePath = Path.Combine(AppPaths.Data, "conclave.log");
    private static readonly Lock Gate = new();

    public static void Info(string message) => Write("INFO", message);

    public static void Error(string message, Exception? error = null) =>
        Write("ERROR", error is null ? message : $"{message}: {error}");

    private static void Write(string level, string message)
    {
        try
        {
            lock (Gate)
            {
                Directory.CreateDirectory(AppPaths.Data);
                if (File.Exists(FilePath) && new FileInfo(FilePath).Length > MaxBytes)
                {
                    File.Move(FilePath, FilePath + ".old", overwrite: true);
                }

                File.AppendAllText(FilePath, $"{DateTimeOffset.Now:yyyy-MM-dd HH:mm:ss} {level} {message}{Environment.NewLine}");
            }
        }
        catch (IOException)
        {
            // Logging must never break the application
        }
    }
}
