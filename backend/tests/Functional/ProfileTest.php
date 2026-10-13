<?php

namespace App\Tests\Functional;

use App\Tests\Support\HttpClientMock;
use Symfony\Component\HttpFoundation\File\UploadedFile;

final class ProfileTest extends ApiTestCase
{
    public function testMemberEditsTheirProfile(): void
    {
        $leader = $this->registerClan();

        $profile = $this->api('PUT', '/api/me/profile', [
            'displayName' => '  Ezio  ',
            'bio' => 'Rien n\'est vrai.',
            'gamerTags' => ['psn' => 'Ezio_Auditore', 'xbox' => ' ', 'steam' => '12345678'],
        ], $leader['token']);

        self::assertResponseIsSuccessful();
        self::assertSame('Ezio', $profile['displayName']);
        self::assertSame(['psn' => 'Ezio_Auditore', 'steam' => '12345678'], $profile['gamerTags']);
        self::assertCount(1, HttpClientMock::publishedOfType('member'));

        $this->api('PUT', '/api/me/profile', ['displayName' => 'Ezio', 'gamerTags' => ['myspace' => 'x']], $leader['token']);
        self::assertResponseStatusCodeSame(422);
        $this->api('PUT', '/api/me/profile', ['displayName' => ' '], $leader['token']);
        self::assertResponseStatusCodeSame(422);
    }

    public function testAvatarIsCroppedResizedAndServedPublicly(): void
    {
        $leader = $this->registerClan();

        $me = $this->uploadAvatar($leader['token'], $this->image(400, 300));
        self::assertResponseIsSuccessful();
        self::assertMatchesRegularExpression('#^api/avatars/[a-f0-9]{32}\.webp$#', $me['avatarUrl']);

        // No token needed: <img> tags cannot send it
        $this->client->request('GET', '/'.$me['avatarUrl']);
        $response = $this->client->getResponse();
        self::assertResponseIsSuccessful();
        self::assertSame('image/webp', $response->headers->get('Content-Type'));
        self::assertStringContainsString('immutable', $response->headers->get('Cache-Control'));
        $size = getimagesize($response->getFile()->getPathname());
        self::assertSame([256, 256], [$size[0], $size[1]]);

        // Replacing deletes the previous file
        $previous = $response->getFile()->getPathname();
        $this->uploadAvatar($leader['token'], $this->image(100, 100));
        self::assertFileDoesNotExist($previous);

        $me = $this->api('DELETE', '/api/me/avatar', token: $leader['token']);
        self::assertNull($me['avatarUrl']);
    }

    public function testInvalidAvatarsAreRejected(): void
    {
        $leader = $this->registerClan();
        $file = tempnam(sys_get_temp_dir(), 'avatar');
        file_put_contents($file, 'not an image');

        $this->uploadAvatar($leader['token'], new UploadedFile($file, 'avatar.png', 'image/png', null, true));

        self::assertResponseStatusCodeSame(422);
    }

    public function testDiscordAvatarIsUsedByDefault(): void
    {
        $leader = $this->registerClan();
        $this->linkDiscord($leader['token'], '111');

        $me = $this->api('GET', '/api/me', token: $leader['token']);

        self::assertSame('https://cdn.discordapp.com/avatars/111/abc.png?size=64', $me['avatarUrl']);
    }

    public function testGravatarIsUsedFromAnEmailAddressThatIsNotStored(): void
    {
        $leader = $this->registerClan();

        $result = $this->api('PUT', '/api/me/avatar/gravatar', ['email' => ' Ezio@Example.com '], $leader['token']);

        self::assertResponseIsSuccessful();
        $hash = hash('sha256', 'ezio@example.com');
        self::assertSame("https://gravatar.com/avatar/$hash?s=256&d=404", $result['avatarUrl']);
        self::assertSame('gravatar', $result['avatar']['source']);
        self::assertCount(1, HttpClientMock::publishedOfType('member'));

        // Only the hash is kept
        $row = static::getContainer()->get('doctrine.dbal.default_connection')
            ->fetchAssociative('SELECT * FROM "user" WHERE id = ?', [$leader['user']['id']]);
        self::assertStringNotContainsString('ezio', strtolower(json_encode($row)));

        $result = $this->api('DELETE', '/api/me/avatar/gravatar', token: $leader['token']);
        self::assertNull($result['avatarUrl']);
        self::assertSame('initials', $result['avatar']['source']);
    }

    public function testAddressesWithoutGravatarAreRejected(): void
    {
        $leader = $this->registerClan();

        $result = $this->api('PUT', '/api/me/avatar/gravatar', ['email' => 'nobody@example.com'], $leader['token']);
        self::assertResponseStatusCodeSame(422);
        self::assertSame('Aucun Gravatar n\'est associé à cette adresse.', $result['detail']);

        $this->api('PUT', '/api/me/avatar/gravatar', ['email' => 'not an email'], $leader['token']);
        self::assertResponseStatusCodeSame(422);

        HttpClientMock::$gravatarDown = true;
        $this->api('PUT', '/api/me/avatar/gravatar', ['email' => 'ezio@example.com'], $leader['token']);
        self::assertResponseStatusCodeSame(503);
    }

    public function testMemberChoosesAmongTheirAvatars(): void
    {
        $leader = $this->registerClan();
        $this->linkDiscord($leader['token'], '111');
        $this->api('PUT', '/api/me/avatar/gravatar', ['email' => 'ezio@example.com'], $leader['token']);
        $uploaded = $this->uploadAvatar($leader['token'], $this->image(80, 80));

        // The last configured source is shown, the others stay available
        self::assertSame('upload', $uploaded['avatar']['source']);
        self::assertEqualsCanonicalizing(['upload', 'gravatar', 'discord'], array_keys($uploaded['avatar']['available']));

        $result = $this->api('PUT', '/api/me/avatar/source', ['source' => 'discord'], $leader['token']);
        self::assertSame('https://cdn.discordapp.com/avatars/111/abc.png?size=64', $result['avatarUrl']);

        $result = $this->api('PUT', '/api/me/avatar/source', ['source' => 'initials'], $leader['token']);
        self::assertNull($result['avatarUrl']);

        // Without a chosen source, uploaded picture first, then Gravatar, then Discord
        $this->api('PUT', '/api/me/avatar/source', ['source' => 'gravatar'], $leader['token']);
        $this->api('DELETE', '/api/me/avatar/gravatar', token: $leader['token']);
        self::assertSame('upload', $this->api('GET', '/api/me/profile', token: $leader['token'])['avatar']['source']);
    }

    public function testOnlyConfiguredSourcesCanBeChosen(): void
    {
        $leader = $this->registerClan();

        $this->api('PUT', '/api/me/avatar/source', ['source' => 'gravatar'], $leader['token']);
        self::assertResponseStatusCodeSame(422);
        $this->api('PUT', '/api/me/avatar/source', ['source' => 'myspace'], $leader['token']);
        self::assertResponseStatusCodeSame(422);
    }

    public function testFactionPreferenceFollowsTheAccount(): void
    {
        $leader = $this->registerClan();
        self::assertNull($leader['user']['faction']);

        $me = $this->api('PUT', '/api/me/faction', ['faction' => 'templar'], $leader['token']);
        self::assertSame('templar', $me['faction']);
        // Returned at every session restore, so that every device applies it
        self::assertSame('templar', $this->api('GET', '/api/me', token: $leader['token'])['faction']);

        $me = $this->api('PUT', '/api/me/faction', ['faction' => null], $leader['token']);
        self::assertNull($me['faction']);

        $this->api('PUT', '/api/me/faction', ['faction' => 'abstergo'], $leader['token']);
        self::assertResponseStatusCodeSame(422);
    }

    public function testPasswordChange(): void
    {
        $leader = $this->registerClan();

        $result = $this->api('PUT', '/api/me/password', ['currentPassword' => 'wrong', 'newPassword' => 'nouveau-secret'], $leader['token']);
        self::assertResponseStatusCodeSame(422);
        self::assertSame('Mot de passe actuel incorrect.', $result['detail']);

        $this->api('PUT', '/api/me/password', ['currentPassword' => 'motdepasse', 'newPassword' => 'nouveau-secret'], $leader['token']);
        self::assertResponseStatusCodeSame(204);
        self::assertNotEmpty($this->login('papa', 'nouveau-secret'));
    }

    public function testMemberProfilesAreVisibleToTheClan(): void
    {
        $leader = $this->registerClan();
        $lea = $this->addMember($leader['token'], 'lea');
        $this->api('PUT', '/api/me/profile', ['displayName' => 'Léa', 'bio' => 'Furtive.', 'gamerTags' => ['nintendo' => 'SW-1234-5678-9012']], $lea['token']);

        $profile = $this->api('GET', "/api/members/{$lea['user']['id']}", token: $leader['token']);
        self::assertSame(['Léa', 'Furtive.', ['nintendo' => 'SW-1234-5678-9012'], []], [$profile['displayName'], $profile['bio'], $profile['gamerTags'], $profile['games']]);
        self::assertArrayNotHasKey('email', $profile);

        // Pending accounts are not visible
        $pendingId = $this->signUp('marc');
        $this->api('GET', "/api/members/$pendingId", token: $leader['token']);
        self::assertResponseStatusCodeSame(404);
    }

    public function testEmailIsEditableAndUnique(): void
    {
        $leader = $this->registerClan();
        $this->addMember($leader['token'], 'lea');

        $profile = $this->api('PUT', '/api/me/profile', ['displayName' => 'Papa', 'email' => ' Nouveau@Example.com '], $leader['token']);
        self::assertSame('nouveau@example.com', $profile['email']);

        $this->api('PUT', '/api/me/profile', ['displayName' => 'Papa', 'email' => 'lea@example.com'], $leader['token']);
        self::assertResponseStatusCodeSame(422);
    }

    private function uploadAvatar(string $token, UploadedFile $file): mixed
    {
        $this->client->request('POST', '/api/me/avatar', files: ['avatar' => $file], server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
            'HTTP_ACCEPT' => 'application/json',
        ]);

        return json_decode($this->client->getResponse()->getContent(), true);
    }

    private function image(int $width, int $height): UploadedFile
    {
        $image = imagecreatetruecolor($width, $height);
        imagefill($image, 0, 0, imagecolorallocate($image, 163, 23, 29));
        $file = tempnam(sys_get_temp_dir(), 'avatar').'.png';
        imagepng($image, $file);

        return new UploadedFile($file, 'avatar.png', 'image/png', null, true);
    }
}
