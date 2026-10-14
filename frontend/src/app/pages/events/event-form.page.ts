import { Component, computed, effect, inject, input, signal, untracked } from '@angular/core';
import { toSignal } from '@angular/core/rxjs-interop';
import { NonNullableFormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { Router, RouterLink } from '@angular/router';
import { firstValueFrom } from 'rxjs';
import { apiErrorMessage } from '../../core/api/api-error';
import { EventField, EventInput, EventTypeInfo, EventTypes, Option } from '../../core/api/models';
import { EventsService } from '../../core/events/events.service';
import { FilmsService } from '../../core/films/films.service';
import { GamesService } from '../../core/games/games.service';
import { BranchCrest } from '../../shared/branch-crest';
import { Icon } from '../../shared/icon';
import { CatalogPicker, PickerItem } from './catalog-picker';
import { fromLocalInput, toLocalInput } from './event-format';

interface ChosenGame {
  source: string;
  externalId: string;
  name: string;
  /** Platforms of the game, when known */
  platforms: string[];
}

interface ChosenFilm {
  tmdbId: number;
  title: string;
  year: string | null;
}

/** Creation (/evenements/nouveau) or update (/evenements/:id/modifier) of an event. */
@Component({
  selector: 'app-event-form-page',
  imports: [ReactiveFormsModule, RouterLink, BranchCrest, Icon, CatalogPicker],
  templateUrl: './event-form.page.html',
  styleUrl: './event-form.page.scss',
})
export class EventFormPage {
  private readonly eventsService = inject(EventsService);
  private readonly games = inject(GamesService);
  private readonly films = inject(FilmsService);
  private readonly router = inject(Router);

  /** Route parameter, when editing */
  readonly id = input<string>();
  /** ?type=…: type chosen beforehand */
  readonly type = input<string>();
  /** ?date=2026-10-17: day chosen in the planning */
  readonly date = input<string>();

  protected readonly form = inject(NonNullableFormBuilder).group({
    type: ['', Validators.required],
    title: ['', [Validators.required, Validators.maxLength(120), Validators.pattern(/\S/)]],
    startsAt: ['', Validators.required],
    endsAt: [''],
    maxParticipants: [null as number | null, [Validators.min(2), Validators.max(500)]],
    description: ['', Validators.maxLength(5000)],
    gamePlatform: [''],
    filmPlatform: [''],
    streamPlatform: [''],
    link: ['', Validators.maxLength(500)],
    theme: ['', Validators.maxLength(120)],
  });
  private readonly value = toSignal(this.form.valueChanges, {
    initialValue: this.form.getRawValue(),
  });

  protected readonly editing = computed(() => !!this.id());
  protected readonly catalog = signal<EventTypes | null>(null);
  protected readonly loaded = signal(false);
  protected readonly busy = signal(false);
  protected readonly error = signal<string | null>(null);
  protected readonly game = signal<ChosenGame | null>(null);
  protected readonly film = signal<ChosenFilm | null>(null);

  /** Types the member can create, by branch */
  protected readonly groups = computed(() => {
    const groups: { branch: EventTypeInfo['branch']; types: EventTypeInfo[] }[] = [];
    for (const type of this.catalog()?.types.filter((t) => t.canCreate) ?? []) {
      const group = groups.find((g) => g.branch.id === type.branch.id);
      if (group) {
        group.types.push(type);
      } else {
        groups.push({ branch: type.branch, types: [type] });
      }
    }
    return groups;
  });
  protected readonly selected = computed(
    () => this.catalog()?.types.find((t) => t.type === this.value().type) ?? null,
  );
  protected readonly gamePlatforms = computed<Option[]>(() => {
    const known = this.game()?.platforms ?? [];
    const all = this.games.platforms();
    return known.length ? all.filter((p) => known.includes(p.value)) : all;
  });

  protected readonly searchGames = async (query: string): Promise<PickerItem[]> =>
    (await firstValueFrom(this.games.search(query))).map((g) => {
      const key = `${g.source}:${g.externalId}`;
      this.gamePlatformsByKey.set(key, g.platforms);
      return {
        key,
        name: g.name,
        detail:
          [g.releaseDate?.slice(0, 4), g.platforms.map((p) => this.games.label(p)).join(', ')]
            .filter(Boolean)
            .join(' · ') || null,
        imageUrl: g.coverUrl,
      };
    });
  protected readonly searchFilms = async (query: string): Promise<PickerItem[]> =>
    (await this.films.search(query)).map((f) => ({
      key: String(f.tmdbId),
      name: f.title,
      detail: [f.year, f.originalTitle].filter(Boolean).join(' · ') || null,
      imageUrl: f.posterUrl,
    }));

  /** Platforms of the games found, to offer only those of the chosen game */
  private readonly gamePlatformsByKey = new Map<string, string[]>();

  constructor() {
    effect(() => {
      const id = this.id();
      const type = this.type();
      untracked(() => void this.load(id ? Number(id) : null, type ?? null));
    });
  }

  protected has(field: EventField): boolean {
    return this.selected()?.fields.some((f) => f.name === field) ?? false;
  }

  protected required(field: EventField): boolean {
    return this.selected()?.fields.some((f) => f.name === field && f.required) ?? false;
  }

  protected chooseType(type: string): void {
    this.form.controls.type.setValue(type);
    this.error.set(null);
  }

  protected pickGame(item: PickerItem): void {
    const [source, externalId] = item.key.split(':');
    const platforms = this.gamePlatformsByKey.get(item.key) ?? [];
    this.game.set({ source, externalId, name: item.name, platforms });
    if (platforms.length === 1) {
      this.form.controls.gamePlatform.setValue(platforms[0]);
    }
    this.suggestTitle(item.name);
  }

  protected pickFilm(item: PickerItem): void {
    this.film.set({
      tmdbId: Number(item.key),
      title: item.name,
      year: item.detail?.slice(0, 4) ?? null,
    });
    this.suggestTitle(item.name);
  }

  protected async submit(): Promise<void> {
    const v = this.form.getRawValue();
    if (this.form.invalid) {
      this.form.markAllAsTouched();
      this.error.set('Vérifiez les champs : le type, le titre et le début sont obligatoires.');
      return;
    }
    const missing = this.missing();
    if (missing) {
      this.error.set(`Précisez ${missing}.`);
      return;
    }

    const input: EventInput = {
      type: v.type,
      title: v.title.trim(),
      description: v.description.trim() || null,
      startsAt: fromLocalInput(v.startsAt)!,
      endsAt: fromLocalInput(v.endsAt),
      maxParticipants: v.maxParticipants || null,
      gameSource: this.game()?.source ?? null,
      gameExternalId: this.game()?.externalId ?? null,
      gamePlatform: v.gamePlatform || null,
      tmdbId: this.film()?.tmdbId ?? null,
      filmPlatform: v.filmPlatform || null,
      streamPlatform: v.streamPlatform || null,
      link: v.link.trim() || null,
      theme: v.theme.trim() || null,
    };
    this.busy.set(true);
    this.error.set(null);
    try {
      const id = this.id();
      const event = id
        ? await this.eventsService.update(Number(id), input)
        : await this.eventsService.create(input);
      await this.router.navigate(['/evenements', event.id]);
    } catch (e) {
      this.error.set(apiErrorMessage(e, 'L’événement n’a pas pu être enregistré.'));
    } finally {
      this.busy.set(false);
    }
  }

  /** Label of a required specific field left empty */
  private missing(): string | null {
    const v = this.form.getRawValue();
    const filled: Record<EventField, boolean> = {
      game: !!this.game(),
      gamePlatform: !!v.gamePlatform,
      film: !!this.film(),
      filmPlatform: !!v.filmPlatform,
      streamPlatform: !!v.streamPlatform,
      link: !!v.link.trim(),
      theme: !!v.theme.trim(),
    };
    const labels: Record<EventField, string> = {
      game: 'le jeu',
      gamePlatform: 'la plateforme',
      film: 'le film',
      filmPlatform: 'la plateforme de visionnage',
      streamPlatform: 'la plateforme de diffusion',
      link: 'le lien',
      theme: 'le thème',
    };
    const field = this.selected()?.fields.find((f) => f.required && !filled[f.name]);
    return field ? labels[field.name] : null;
  }

  private suggestTitle(name: string): void {
    if (!this.form.controls.title.value.trim()) {
      this.form.controls.title.setValue(`${this.selected()?.label ?? 'Événement'} : ${name}`);
    }
  }

  private async load(id: number | null, type: string | null): Promise<void> {
    try {
      this.catalog.set(await this.eventsService.types());
      if (id) {
        const event = await this.eventsService.get(id);
        if (!event.canEdit) {
          await this.router.navigate(['/evenements', id]);
          return;
        }
        this.form.reset({
          type: event.type,
          title: event.title,
          startsAt: toLocalInput(event.startsAt),
          endsAt: toLocalInput(event.endsAt),
          maxParticipants: event.maxParticipants,
          description: event.description ?? '',
          gamePlatform: event.gamePlatform?.value ?? '',
          filmPlatform: event.filmPlatform?.value ?? '',
          streamPlatform: event.streamPlatform?.value ?? '',
          link: event.link ?? '',
          theme: event.theme ?? '',
        });
        this.game.set(
          event.game
            ? {
                source: event.game.source,
                externalId: event.game.externalId,
                name: event.game.name,
                platforms: [],
              }
            : null,
        );
        this.film.set(
          event.film
            ? { tmdbId: event.film.tmdbId, title: event.film.title, year: event.film.year }
            : null,
        );
      } else {
        const creatable = this.catalog()!.types.filter((t) => t.canCreate);
        const preset =
          creatable.find((t) => t.type === type) ?? (creatable.length === 1 ? creatable[0] : null);
        if (preset) {
          this.chooseType(preset.type);
        }
        // From the planning: that day, in the evening by default
        const day = this.date();
        if (day && /^\d{4}-\d{2}-\d{2}$/.test(day)) {
          this.form.controls.startsAt.setValue(`${day}T21:00`);
        }
      }
      this.loaded.set(true);
    } catch (e) {
      this.error.set(apiErrorMessage(e, 'Impossible de préparer le formulaire.'));
    }
  }
}
