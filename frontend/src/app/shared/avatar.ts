import { Component, computed, inject, input, linkedSignal } from '@angular/core';
import { ConsentService } from '../core/consent/consent.service';
import { Member } from '../core/api/models';

/** Member avatar: uploaded or Discord picture, else the initial. */
@Component({
  selector: 'app-avatar',
  template: `
    @if (src(); as url) {
      <img
        [src]="url"
        alt=""
        [attr.width]="size()"
        [attr.height]="size()"
        loading="lazy"
        decoding="async"
        referrerpolicy="no-referrer"
        (error)="failed.set(true)"
      />
    } @else {
      <span aria-hidden="true">{{ initial() }}</span>
    }
  `,
  styles: `
    :host {
      display: inline-grid;
      flex: none;
      place-items: center;
      overflow: hidden;
      width: var(--avatar-size);
      height: var(--avatar-size);
      border: 2px solid var(--ornament);
      border-radius: 50%;
      background: var(--accent);
      color: var(--accent-contrast);
      font-family: var(--font-display);
      font-size: calc(var(--avatar-size) * 0.42);
      font-weight: 700;
      text-transform: uppercase;
    }
    img {
      width: 100%;
      height: 100%;
      object-fit: cover;
    }
  `,
  host: { '[style.--avatar-size.px]': 'size()' },
})
export class Avatar {
  readonly member = input.required<Pick<Member, 'displayName' | 'avatarUrl'>>();
  readonly size = input(40);

  /** Image failed to load (reset when the picture changes) */
  protected readonly failed = linkedSignal({
    source: () => this.member().avatarUrl,
    computation: () => false,
  });
  protected readonly initial = computed(() => this.member().displayName.charAt(0));
  private readonly consent = inject(ConsentService);
  /** Gravatar and Discord pictures are only loaded with consent (see the cookie banner) */
  protected readonly src = computed(() =>
    this.failed() ? null : this.consent.src(this.member().avatarUrl),
  );
}
