import { HttpClient } from '@angular/common/http';
import { Injectable, inject, signal } from '@angular/core';
import { DOCUMENT } from '@angular/common';
import { firstValueFrom } from 'rxjs';
import { AuthService } from '../auth/auth.service';

/** Where the widget comes from (GET /api/feedback); widgetUrl null: not configured on this server */
export interface FeedbackConfig {
  widgetUrl: string | null;
  product: string | null;
}

/** The ticket widget of Avenger (public/widget.js in the Avenger project) */
interface AvengerTickets {
  open(options?: { type?: 'bug' | 'feedback' | 'feature'; name?: string; email?: string }): void;
}

declare global {
  interface Window {
    AvengerTickets?: AvengerTickets;
  }
}

/**
 * « Signaler un problème » (menu of the account): opens the ticket form of the Avenger app that
 * follows Conclave, prefilled with the member's name and e-mail. The widget's script is only
 * loaded on the first click, so Avenger is not contacted on every visit.
 */
@Injectable({ providedIn: 'root' })
export class FeedbackService {
  private readonly http = inject(HttpClient);
  private readonly auth = inject(AuthService);
  private readonly document = inject(DOCUMENT);

  private config?: Promise<FeedbackConfig | null>;
  private script?: Promise<AvengerTickets>;
  private readonly _available = signal(false);

  /** The server has an Avenger to send the tickets to */
  readonly available = this._available.asReadonly();

  /** Asks the server once whether the widget is configured. */
  load(): Promise<FeedbackConfig | null> {
    return (this.config ??= firstValueFrom(this.http.get<FeedbackConfig>('api/feedback')).then(
      (config) => {
        this._available.set(!!config.widgetUrl && !!config.product);
        return config;
      },
      () => {
        // Asked again next time: the API was unreachable
        this.config = undefined;
        return null;
      },
    ));
  }

  async open(): Promise<void> {
    const config = await this.load();
    if (!config?.widgetUrl || !config.product) {
      return;
    }
    let widget: AvengerTickets;
    try {
      widget = await this.loadScript(config.widgetUrl, config.product);
    } catch (error) {
      // The widget does not load (blocked, Avenger down…): its form, in a new tab
      this.document.defaultView?.open(
        ticketPage(config.widgetUrl, config.product),
        '_blank',
        'noopener',
      );
      throw error;
    }
    const me = this.auth.user();
    widget.open({ type: 'bug', name: me?.displayName, email: me?.email ?? undefined });
  }

  private loadScript(src: string, product: string): Promise<AvengerTickets> {
    return (this.script ??= new Promise<AvengerTickets>((resolve, reject) => {
      const window = this.document.defaultView;
      if (window?.AvengerTickets) {
        resolve(window.AvengerTickets);
        return;
      }
      const script = this.document.createElement('script');
      script.src = src;
      script.dataset['product'] = product;
      // Opened from the menu, not from a floating button over the side tabs
      script.dataset['button'] = 'false';
      script.onload = () =>
        window?.AvengerTickets
          ? resolve(window.AvengerTickets)
          : reject(new Error('widget.js loaded without AvengerTickets'));
      script.onerror = () => reject(new Error(`Avenger unreachable: ${src}`));
      this.document.head.append(script);
    }).catch((error: unknown) => {
      // Tried again on the next click
      this.script = undefined;
      throw error;
    }));
  }
}

/** The ticket form of the product on Avenger, next to its widget.js */
export function ticketPage(widgetUrl: string, product: string): string {
  return new URL(`produits/${encodeURIComponent(product)}/tickets/nouveau`, widgetUrl).href;
}
