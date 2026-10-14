import {
  Component,
  DestroyRef,
  ElementRef,
  inject,
  input,
  output,
  signal,
  viewChild,
} from '@angular/core';
import { apiErrorMessage } from '../../core/api/api-error';
import { ConsentService } from '../../core/consent/consent.service';

/** A result of a catalog search (game or film) */
export interface PickerItem {
  key: string;
  name: string;
  detail: string | null;
  imageUrl: string | null;
}

const SEARCH_DELAY_MS = 350;

/**
 * Search field of a catalog (games, films): type, see the results, pick one. Arrows and Enter work
 * in the list, as in a combobox.
 */
@Component({
  selector: 'app-catalog-picker',
  template: `
    <div class="picker">
      <input
        #field
        type="search"
        role="combobox"
        [id]="inputId()"
        [attr.aria-expanded]="results().length > 0"
        [attr.aria-controls]="inputId() + '-results'"
        [attr.aria-activedescendant]="active() >= 0 ? inputId() + '-option-' + active() : null"
        aria-autocomplete="list"
        autocomplete="off"
        [placeholder]="placeholder()"
        (input)="query(field.value)"
        (keydown)="key($event)"
      />
      @if (searching()) {
        <p class="hint" role="status">Recherche…</p>
      }
      @if (error()) {
        <p class="error" role="alert">{{ error() }}</p>
      }
      @if (results().length) {
        <ul class="results" role="listbox" [id]="inputId() + '-results'">
          @for (item of results(); track item.key; let i = $index) {
            <li
              role="option"
              [id]="inputId() + '-option-' + i"
              [attr.aria-selected]="i === active()"
              [class.active]="i === active()"
              (mousedown)="$event.preventDefault()"
              (click)="pick(item)"
            >
              @if (consent.src(item.imageUrl); as src) {
                <img [src]="src" alt="" referrerpolicy="no-referrer" />
              } @else {
                <span class="no-image" aria-hidden="true"></span>
              }
              <span class="text">
                <strong>{{ item.name }}</strong>
                @if (item.detail) {
                  <span>{{ item.detail }}</span>
                }
              </span>
            </li>
          }
        </ul>
      } @else if (empty()) {
        <p class="hint">Aucun résultat.</p>
      }
    </div>
  `,
  styles: `
    .picker {
      position: relative;
    }
    .results {
      position: absolute;
      z-index: 10;
      top: calc(100% + 0.25rem);
      left: 0;
      right: 0;
      display: grid;
      max-height: 20rem;
      margin: 0;
      padding: 0.25rem;
      overflow-y: auto;
      border: 1px solid var(--border);
      border-radius: var(--radius);
      background: var(--surface-solid);
      box-shadow: 0 8px 24px rgb(0 0 0 / 0.25);
      list-style: none;
    }
    li {
      display: flex;
      align-items: center;
      gap: 0.6rem;
      padding: 0.35rem 0.5rem;
      border-radius: var(--radius);
      cursor: pointer;
    }
    li.active,
    li:hover {
      background: var(--surface-muted);
    }
    img,
    .no-image {
      flex: none;
      width: 2.5rem;
      height: 2.5rem;
      border-radius: 4px;
      object-fit: cover;
      background: var(--surface-muted);
    }
    .text {
      display: grid;
      min-width: 0;
      font-size: 0.9rem;
    }
    .text span {
      color: var(--text-muted);
      font-size: 0.8rem;
    }
    .hint {
      margin: 0.35rem 0 0;
      color: var(--text-muted);
      font-size: 0.85rem;
    }
    .error {
      margin: 0.35rem 0 0;
    }
  `,
})
export class CatalogPicker {
  protected readonly consent = inject(ConsentService);
  private readonly field = viewChild<ElementRef<HTMLInputElement>>('field');

  readonly inputId = input.required<string>();
  readonly placeholder = input('');
  /** Search in the catalog */
  readonly search = input.required<(query: string) => Promise<PickerItem[]>>();
  readonly picked = output<PickerItem>();

  protected readonly results = signal<PickerItem[]>([]);
  protected readonly active = signal(-1);
  protected readonly searching = signal(false);
  protected readonly empty = signal(false);
  protected readonly error = signal<string | null>(null);
  private timer: ReturnType<typeof setTimeout> | undefined;
  private lastQuery = '';

  constructor() {
    inject(DestroyRef).onDestroy(() => clearTimeout(this.timer));
  }

  protected query(value: string): void {
    clearTimeout(this.timer);
    const query = value.trim();
    this.lastQuery = query;
    this.empty.set(false);
    if (query.length < 2) {
      this.results.set([]);
      return;
    }
    this.timer = setTimeout(() => void this.run(query), SEARCH_DELAY_MS);
  }

  protected key(event: KeyboardEvent): void {
    const count = this.results().length;
    if (!count) {
      return;
    }
    if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
      event.preventDefault();
      const step = event.key === 'ArrowDown' ? 1 : -1;
      this.active.update((i) => (i + step + count) % count);
    } else if (event.key === 'Enter' && this.active() >= 0) {
      event.preventDefault();
      this.pick(this.results()[this.active()]);
    } else if (event.key === 'Escape') {
      this.results.set([]);
    }
  }

  protected pick(item: PickerItem): void {
    this.picked.emit(item);
    this.results.set([]);
    this.active.set(-1);
    const field = this.field()?.nativeElement;
    if (field) {
      field.value = '';
    }
  }

  private async run(query: string): Promise<void> {
    this.searching.set(true);
    this.error.set(null);
    try {
      const results = await this.search()(query);
      if (query === this.lastQuery) {
        this.results.set(results);
        this.active.set(results.length ? 0 : -1);
        this.empty.set(results.length === 0);
      }
    } catch (e) {
      this.error.set(apiErrorMessage(e, 'La recherche est indisponible.'));
    } finally {
      this.searching.set(false);
    }
  }
}
