import { MemberCardDirective } from '../../shared/member-card.directive';
import {
  Component,
  ElementRef,
  effect,
  inject,
  input,
  signal,
  untracked,
  viewChild,
} from '@angular/core';
import { RouterLink } from '@angular/router';
import { ChatPanelService } from '../../core/chat/chat-panel.service';
import { ConsentService } from '../../core/consent/consent.service';
import { firstValueFrom } from 'rxjs';
import { apiErrorMessage } from '../../core/api/api-error';
import { GameDetails, GamePlatform } from '../../core/api/models';
import { AuthService } from '../../core/auth/auth.service';
import { GamesService } from '../../core/games/games.service';
import { Avatar } from '../../shared/avatar';
import { ManageCookies } from '../../shared/manage-cookies';
import { PlatformBadges } from '../../shared/platform-badges';
import { PlatformPicker } from '../../shared/platform-picker';

@Component({
  selector: 'app-game-detail-page',
  imports: [MemberCardDirective, RouterLink, PlatformBadges, PlatformPicker, Avatar, ManageCookies],
  templateUrl: './game-detail.page.html',
  styleUrl: './game-detail.page.scss',
})
export class GameDetailPage {
  protected readonly panel = inject(ChatPanelService);
  protected readonly consent = inject(ConsentService);
  private readonly games = inject(GamesService);
  protected readonly me = inject(AuthService).user;
  private readonly lightbox = viewChild<ElementRef<HTMLDialogElement>>('lightbox');

  /** Route parameter (bound by the router). */
  readonly id = input.required<string>();

  protected readonly game = signal<GameDetails | null>(null);
  protected readonly error = signal<string | null>(null);
  protected readonly platforms = signal<GamePlatform[]>([]);
  protected readonly saving = signal(false);
  protected readonly saved = signal(false);
  protected readonly shot = signal(0);
  protected readonly lightboxOpen = signal(false);

  constructor() {
    effect(() => {
      const id = Number(this.id());
      untracked(() => void this.load(id));
    });
  }

  protected async savePlatforms(): Promise<void> {
    const game = this.game();
    if (!game || this.platforms().length === 0 || this.saving()) {
      return;
    }

    this.saving.set(true);
    this.saved.set(false);
    this.error.set(null);
    try {
      const mine = game.mine
        ? await firstValueFrom(this.games.updatePlatforms(game.mine.id, this.platforms()))
        : await firstValueFrom(this.games.add(game, this.platforms()));
      // Reload to refresh the list of players
      await this.load(game.id);
      this.platforms.set([...mine.platforms]);
      this.saved.set(true);
    } catch (e) {
      this.error.set(apiErrorMessage(e));
    } finally {
      this.saving.set(false);
    }
  }

  protected async remove(): Promise<void> {
    const game = this.game();
    if (!game?.mine || !confirm(`Retirer « ${game.name} » de vos jeux ?`)) {
      return;
    }
    try {
      await firstValueFrom(this.games.remove(game.mine.id));
      await this.load(game.id);
    } catch (e) {
      this.error.set(apiErrorMessage(e));
    }
  }

  protected openShot(index: number): void {
    this.shot.set(index);
    this.lightboxOpen.set(true);
    this.lightbox()?.nativeElement.showModal();
  }

  protected moveShot(step: number): void {
    const count = this.game()?.screenshots.length ?? 0;
    if (count) {
      this.shot.update((i) => (i + step + count) % count);
    }
  }

  protected onLightboxKey(event: KeyboardEvent): void {
    if (event.key === 'ArrowRight') {
      this.moveShot(1);
    } else if (event.key === 'ArrowLeft') {
      this.moveShot(-1);
    }
  }

  /** Light dismiss for browsers without <dialog closedby> support. */
  protected onLightboxClick(event: MouseEvent): void {
    const dialog = event.currentTarget as HTMLDialogElement;
    if ('closedBy' in HTMLDialogElement.prototype || event.target !== dialog) {
      return;
    }
    const rect = dialog.getBoundingClientRect();
    const inside =
      rect.top <= event.clientY &&
      event.clientY <= rect.bottom &&
      rect.left <= event.clientX &&
      event.clientX <= rect.right;
    if (!inside) {
      dialog.close();
    }
  }

  private async load(id: number): Promise<void> {
    try {
      const game = await firstValueFrom(this.games.details(id));
      this.game.set(game);
      this.platforms.set(
        game.mine
          ? [...game.mine.platforms]
          : game.availablePlatforms.length === 1
            ? [...game.availablePlatforms]
            : [],
      );
    } catch (e) {
      this.error.set(apiErrorMessage(e, 'Impossible de charger ce jeu.'));
    }
  }
}
