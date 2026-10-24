using Conclave.Desktop.Core;

namespace Conclave.Desktop.Core.Tests;

public class ServerAddressTests
{
    [Theory]
    [InlineData("192.168.1.10:8080", "http://192.168.1.10:8080/")]
    [InlineData("  https://clan.example.com  ", "https://clan.example.com/")]
    [InlineData("http://nas.local:8080/conclave", "http://nas.local:8080/conclave/")]
    [InlineData("https://clan.example.com/evenements?mois=2026-10#top", "https://clan.example.com/evenements/")]
    public void TurnsWhatIsTypedIntoTheServerAddress(string typed, string expected)
    {
        Assert.True(ServerAddress.TryParse(typed, out var address, out _));
        Assert.Equal(expected, address.AbsoluteUri);
    }

    [Theory]
    [InlineData("", "Indiquez")]
    [InlineData("ftp://clan.example.com", "http://")]
    [InlineData("http://", "pas valide")]
    public void ExplainsWhatIsWrong(string typed, string message)
    {
        Assert.False(ServerAddress.TryParse(typed, out _, out var error));
        Assert.Contains(message, error);
    }

    [Fact]
    public void LabelsTheServerShortly()
    {
        Assert.Equal("192.168.1.10:8080", ServerAddress.Label(new Uri("http://192.168.1.10:8080/")));
        Assert.Equal("clan.example.com", ServerAddress.Label(new Uri("https://clan.example.com/")));
    }
}
