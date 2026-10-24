using Conclave.Desktop.Core;

namespace Conclave.Desktop.Core.Tests;

public class BridgeMessageTests
{
    [Fact]
    public void ReadsANotification()
    {
        var message = BridgeMessage.Parse("""{"type":"notification","title":"Session de jeu sur ELDEN RING (PC)","body":"Léa organise « Raid »","tag":"event-4","link":"/evenements/4"}""");

        Assert.Equal(new NotificationMessage("Session de jeu sur ELDEN RING (PC)", "Léa organise « Raid »", "event-4", "/evenements/4"), message);
    }

    [Fact]
    public void ShortensLongTexts()
    {
        var message = (NotificationMessage)BridgeMessage.Parse($$"""{"type":"notification","title":"T","body":"{{new string('a', 500)}}"}""")!;

        Assert.Equal(300, message.Body.Length);
        Assert.EndsWith("…", message.Body);
    }

    [Fact]
    public void ReadsTheUnreadCount()
    {
        Assert.Equal(new BadgeMessage(3), BridgeMessage.Parse("""{"type":"badge","count":3}"""));
        Assert.Equal(new BadgeMessage(0), BridgeMessage.Parse("""{"type":"badge","count":-2}"""));
    }

    [Theory]
    [InlineData(null)]
    [InlineData("")]
    [InlineData("not json")]
    [InlineData("[1, 2]")]
    [InlineData("""{"type":"notification"}""")]
    [InlineData("""{"type":"badge","count":"3"}""")]
    [InlineData("""{"type":"unknown"}""")]
    public void IgnoresWhatItDoesNotKnow(string? json)
    {
        Assert.Null(BridgeMessage.Parse(json));
    }
}
