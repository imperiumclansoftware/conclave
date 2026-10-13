import { HttpClient } from '@angular/common/http';
import { Injectable, inject, signal } from '@angular/core';
import { firstValueFrom } from 'rxjs';
import { DiscordIntegration } from '../api/models';

export type DiscordFlow = 'link' | 'install';

@Injectable({ providedIn: 'root' })
export class DiscordService {
  private readonly http = inject(HttpClient);

  private readonly _status = signal<DiscordIntegration | null>(null);
  /** Integration status (null until loaded). */
  readonly status = this._status.asReadonly();

  async refresh(): Promise<DiscordIntegration | null> {
    try {
      this._status.set(await firstValueFrom(this.http.get<DiscordIntegration>('api/discord')));
    } catch {
      this._status.set(null);
    }
    return this._status();
  }

  /** Redirects to Discord to link the account, or (leaders) to add the bot to the clan's server. */
  async authorize(purpose: DiscordFlow): Promise<void> {
    const { url } = await firstValueFrom(
      this.http.post<{ url: string }>('api/discord/authorize', { purpose }),
    );
    window.location.assign(url);
  }

  async completeAuthorization(code: string, state: string): Promise<DiscordFlow> {
    const { purpose } = await firstValueFrom(
      this.http.post<{ purpose: DiscordFlow }>('api/discord/callback', { code, state }),
    );
    await this.refresh();
    return purpose;
  }

  async unlink(): Promise<void> {
    await firstValueFrom(this.http.delete('api/discord/account'));
    await this.refresh();
  }

  async disconnectServer(): Promise<void> {
    await firstValueFrom(this.http.delete('api/discord/guild'));
    await this.refresh();
  }
}
