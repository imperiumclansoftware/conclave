import {
  Component,
  DestroyRef,
  computed,
  effect,
  inject,
  input,
  signal,
  untracked,
} from '@angular/core';
import { toSignal } from '@angular/core/rxjs-interop';
import { NonNullableFormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { Router, RouterLink } from '@angular/router';
import { apiErrorMessage } from '../../core/api/api-error';
import { AuthService } from '../../core/auth/auth.service';
import { NEWS_BODY_MAX, NEWS_TITLE_MAX, NewsService } from '../../core/news/news.service';
import { Icon } from '../../shared/icon';
import { NewsCover } from '../../shared/news-cover';

/** Must match the NewsImage constraint on the API */
const IMAGE_MAX_BYTES = 8 * 1024 * 1024;
const IMAGE_TYPES = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];

/** Writing a news (/news/nouvelle) or editing one (/news/:id/modifier). */
@Component({
  selector: 'app-news-form-page',
  imports: [ReactiveFormsModule, RouterLink, Icon, NewsCover],
  templateUrl: './news-form.page.html',
  styleUrl: './news-form.page.scss',
})
export class NewsFormPage {
  private readonly newsService = inject(NewsService);
  private readonly router = inject(Router);
  private readonly auth = inject(AuthService);

  /** Route parameter, when editing */
  readonly id = input<string>();

  protected readonly titleMax = NEWS_TITLE_MAX;
  protected readonly bodyMax = NEWS_BODY_MAX;
  protected readonly form = inject(NonNullableFormBuilder).group({
    title: [
      '',
      [Validators.required, Validators.maxLength(NEWS_TITLE_MAX), Validators.pattern(/\S/)],
    ],
    body: [
      '',
      [Validators.required, Validators.maxLength(NEWS_BODY_MAX), Validators.pattern(/\S/)],
    ],
  });
  private readonly value = toSignal(this.form.valueChanges, {
    initialValue: this.form.getRawValue(),
  });

  protected readonly editing = computed(() => !!this.id());
  protected readonly loaded = signal(false);
  protected readonly busy = signal(false);
  protected readonly error = signal<string | null>(null);
  protected readonly imageError = signal<string | null>(null);
  /** Picture saved on the news */
  protected readonly savedImage = signal<string | null>(null);
  /** Picture chosen in this form */
  protected readonly newImage = signal<File | null>(null);
  protected readonly newImageUrl = signal<string | null>(null);
  protected readonly removeImage = signal(false);
  protected readonly preview = computed(
    () => this.newImageUrl() ?? (this.removeImage() ? null : this.savedImage()),
  );
  protected readonly titleLength = computed(() => this.value().title?.length ?? 0);
  protected readonly bodyLength = computed(() => this.value().body?.length ?? 0);
  protected readonly clanName = computed(() => this.auth.user()?.clan.name ?? '');
  /** The default visual derives from the title: the preview is the published visual */
  protected readonly title = computed(() => this.value().title?.trim() ?? '');

  constructor() {
    effect(() => {
      const id = this.id();
      untracked(() => (id ? void this.load(Number(id)) : this.loaded.set(true)));
    });
    inject(DestroyRef).onDestroy(() => this.revokePreview());
  }

  protected chooseImage(input: HTMLInputElement): void {
    const file = input.files?.[0] ?? null;
    input.value = '';
    this.imageError.set(null);
    if (!file) {
      return;
    }
    if (!IMAGE_TYPES.includes(file.type)) {
      this.imageError.set('Formats acceptés : JPEG, PNG, WebP ou GIF.');
      return;
    }
    if (file.size > IMAGE_MAX_BYTES) {
      this.imageError.set('L’image dépasse 8 Mo.');
      return;
    }
    this.revokePreview();
    this.newImage.set(file);
    this.newImageUrl.set(URL.createObjectURL(file));
    this.removeImage.set(false);
  }

  protected clearImage(): void {
    this.revokePreview();
    this.newImage.set(null);
    this.newImageUrl.set(null);
    this.removeImage.set(this.savedImage() !== null);
  }

  protected async submit(): Promise<void> {
    if (this.form.invalid) {
      this.form.markAllAsTouched();
      return;
    }
    const { title, body } = this.form.getRawValue();
    const input = {
      title: title.trim(),
      body: body.trim(),
      image: this.newImage(),
      removeImage: this.removeImage(),
    };
    this.busy.set(true);
    this.error.set(null);
    try {
      const id = this.id();
      const news = id
        ? await this.newsService.update(Number(id), input)
        : await this.newsService.create(input);
      await this.router.navigate(['/news', news.id]);
    } catch (e) {
      this.error.set(apiErrorMessage(e, 'La news n’a pas pu être enregistrée.'));
    } finally {
      this.busy.set(false);
    }
  }

  private async load(id: number): Promise<void> {
    try {
      const news = await this.newsService.get(id);
      if (!news.canEdit) {
        await this.router.navigate(['/news', id]);
        return;
      }
      this.form.reset({ title: news.title, body: news.body });
      this.savedImage.set(news.imageUrl);
      this.loaded.set(true);
    } catch (e) {
      this.error.set(apiErrorMessage(e, 'News introuvable.'));
    }
  }

  private revokePreview(): void {
    const url = this.newImageUrl();
    if (url) {
      URL.revokeObjectURL(url);
    }
  }
}
