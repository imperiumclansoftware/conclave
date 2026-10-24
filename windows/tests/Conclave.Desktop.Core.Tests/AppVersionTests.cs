using Conclave.Desktop.Core;

namespace Conclave.Desktop.Core.Tests;

public class AppVersionTests
{
    [Theory]
    [InlineData("0.5.0", "0.4.0", true)]
    [InlineData("0.4.1", "0.4.0", true)]
    [InlineData("1.0.0", "0.10.0", true)]
    [InlineData("v0.5.0", "0.4.0", true)]
    [InlineData("0.4.0", "0.4.0", false)]
    [InlineData("0.3.0", "0.4.0", false)]
    [InlineData("0.4.0", "0.4.0+abc123", false)]
    [InlineData("dev", "0.4.0", false)]
    [InlineData(null, "0.4.0", false)]
    public void OffersOnlyNewerVersions(string? offered, string current, bool newer)
    {
        Assert.Equal(newer, AppVersion.IsNewer(offered, current));
    }
}
