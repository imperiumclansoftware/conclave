import { Component, OnInit, computed, inject, signal } from '@angular/core';
import { RouterLink } from '@angular/router';
import { ConsentService } from '../../core/consent/consent.service';
import { firstValueFrom } from 'rxjs';
import { apiErrorMessage } from '../../core/api/api-error';
import { ClanGame, GamePlatform } from '../../core/api/models';
import { AuthService } from '../../core/auth/auth.service';
import { GamesService } from '../../core/games/games.service';
import { PlatformBadges } from '../../shared/platform-badges';
import { GamesTabs } from './games-tabs';

@Component({
  selector: 'app-clan-library-page',
  imports: [RouterLink, GamesTabs, PlatformBadges],
  templateUrl: './clan-library.page.html',
  styleUrl: './clan-library.page.scss',
})
export class ClanLibraryPage implements OnInit {
  protected readonly consent = inject(ConsentService);
  protected readonly games = inject(GamesService);
  protected readonly me = inject(AuthService).user;

  protected readonly library = signal<ClanGame[]>([]);
  protected readonly loaded = signal(false);
  protected readonly error = signal<string | null>(null);
  protected readonly platform = signal<GamePlatform | ''>('');

  /** Games (and players) matching the platform filter. */
  protected readonly filtered = computed(() => {
    const platform = this.platform();
    if (!platform) {
      return this.library();
    }
    return this.library()
      .map((entry) => ({
        ...entry,
        players: entry.players.filter((p) => p.platforms.includes(platform)),
      }))
      .filter((entry) => entry.players.length > 0);
  });

  protected onFilter(value: string): void {
    this.platform.set(value as GamePlatform | '');
  }

  async ngOnInit(): Promise<void> {
    try {
      this.library.set(await firstValueFrom(this.games.clanLibrary()));
    } catch (e) {
      this.error.set(apiErrorMessage(e));
    } finally {
      this.loaded.set(true);
    }
  }
}
