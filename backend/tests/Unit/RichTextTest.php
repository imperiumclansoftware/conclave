<?php

namespace App\Tests\Unit;

use App\Text\RichText;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class RichTextTest extends KernelTestCase
{
    private RichText $richText;

    protected function setUp(): void
    {
        $this->richText = static::getContainer()->get(RichText::class);
    }

    public function testKeepsTheFormattingAndTheLinks(): void
    {
        $html = '<h2>Programme</h2><p><strong>Samedi</strong>, <em>21 h</em> : <a href="https://discord.gg/abc">le salon</a> '
            .'et <a href="https://example.org/inscription" class="button">S\'inscrire</a>.</p><ul><li>Un</li></ul>';

        self::assertSame(
            '<h2>Programme</h2><p><strong>Samedi</strong>, <em>21 h</em> : '
            .'<a href="https://discord.gg/abc" rel="noopener noreferrer nofollow" target="_blank">le salon</a> et '
            .'<a href="https://example.org/inscription" class="button" rel="noopener noreferrer nofollow" target="_blank">S&#039;inscrire</a>.</p>'
            .'<ul><li>Un</li></ul>',
            $this->richText->clean($html),
        );
    }

    public function testRemovesWhatIsNotFormatting(): void
    {
        $html = '<p onclick="steal()">Bonjour <script>alert(1)</script><img src="x" onerror="steal()">'
            .'<a href="javascript:steal()">piège</a> <a href="/evenements/4" class="btn huge">ici</a>'
            .'<span style="color:red">rouge</span></p><p></p><p><br></p>';

        self::assertSame(
            '<p>Bonjour <a rel="noopener noreferrer nofollow" target="_blank">piège</a> '
            .'<a href="/evenements/4" rel="noopener noreferrer nofollow" target="_blank">ici</a>rouge</p>',
            $this->richText->clean($html),
        );
    }

    public function testNothingWrittenIsNull(): void
    {
        self::assertNull($this->richText->clean('<p></p><p><br></p>'));
        self::assertNull($this->richText->clean('   '));
    }

    public function testReadsThePlainTextAndTheOldTexts(): void
    {
        self::assertSame("Titre\nUn & deux\n\nFin", RichText::plain('<h2>Titre</h2><p>Un &amp; deux</p><p></p><p>Fin</p>'));
        self::assertSame('<p>Ligne 1<br>Ligne 2</p><p>&lt;b&gt; reste du texte</p>', RichText::fromPlainText("Ligne 1\nLigne 2\n\n\n<b> reste du texte"));
    }

    public function testButtonsAreStyledForTheEmails(): void
    {
        $email = RichText::forEmail('<a href="https://example.org" class="button" target="_blank">Go</a>');

        self::assertStringContainsString('style="display:inline-block;', $email);
        self::assertStringNotContainsString('class=', $email);
    }
}
