import {
  Component,
  ElementRef,
  Injector,
  OnDestroy,
  afterNextRender,
  computed,
  forwardRef,
  inject,
  input,
  signal,
  viewChild,
} from '@angular/core';
import { ControlValueAccessor, NG_VALUE_ACCESSOR } from '@angular/forms';
import { Editor } from '@tiptap/core';
import { Link } from '@tiptap/extension-link';
import { StarterKit } from '@tiptap/starter-kit';
import { BUTTON_CLASS, LinkStyle, normalizeLink } from './link';

/** Links carry class="button" when shown as a button (the only class the server keeps) */
const ConclaveLink = Link.extend({
  addAttributes() {
    return {
      ...this.parent?.(),
      class: {
        default: null,
        parseHTML: (element: HTMLElement) =>
          element.getAttribute('class') === BUTTON_CLASS ? BUTTON_CLASS : null,
        renderHTML: (attributes: Record<string, unknown>) =>
          attributes['class'] === BUTTON_CLASS ? { class: BUTTON_CLASS } : {},
      },
    };
  },
}).configure({
  openOnClick: false,
  autolink: true,
  linkOnPaste: true,
  defaultProtocol: 'https',
  protocols: ['mailto'],
  HTMLAttributes: { rel: 'noopener noreferrer nofollow', target: '_blank', class: null },
});

/**
 * Text editor for the news and the event descriptions: formatting, lists, quotes and links, each
 * link shown as text or as a button. The value is HTML (an empty editor gives ''), cleaned again
 * by the server.
 */
@Component({
  selector: 'app-rich-text-editor',
  templateUrl: './rich-text-editor.html',
  styleUrl: './rich-text-editor.scss',
  providers: [
    { provide: NG_VALUE_ACCESSOR, useExisting: forwardRef(() => RichTextEditor), multi: true },
  ],
  host: { '(keydown)': 'onKeydown($event)' },
})
export class RichTextEditor implements ControlValueAccessor, OnDestroy {
  private readonly injector = inject(Injector);
  private readonly area = viewChild.required<ElementRef<HTMLElement>>('area');
  private readonly hrefField = viewChild<ElementRef<HTMLInputElement>>('hrefField');

  /** Id of the label naming the field */
  readonly labelledBy = input<string | null>(null);
  /** Id of the hint under the field */
  readonly describedBy = input<string | null>(null);
  /** Maximum length of the text (formatting left out), shown as a counter */
  readonly maxLength = input<number | null>(null);
  readonly placeholder = input('');

  private editor: Editor | null = null;
  private pending = '';
  private onChange: (value: string) => void = () => undefined;
  private onTouched: () => void = () => undefined;

  /** Changes with every transaction: the toolbar and the counter follow the editor */
  private readonly tick = signal(0);
  protected readonly disabled = signal(false);
  protected readonly length = computed(() => {
    this.tick();
    return this.editor?.getText().length ?? 0;
  });

  // Link panel
  protected readonly linkOpen = signal(false);
  protected readonly linkEditing = signal(false);
  protected readonly linkNeedsText = signal(false);
  protected readonly linkHref = signal('');
  protected readonly linkText = signal('');
  protected readonly linkStyle = signal<LinkStyle>('text');
  protected readonly linkError = signal<string | null>(null);
  protected readonly id = `rte-${Math.random().toString(36).slice(2, 8)}`;

  constructor() {
    afterNextRender(() => this.create());
  }

  private create(): void {
    this.editor = new Editor({
      element: this.area().nativeElement,
      extensions: [
        StarterKit.configure({
          heading: { levels: [2, 3] },
          code: false,
          codeBlock: false,
          horizontalRule: false,
          link: false,
        }),
        ConclaveLink,
      ],
      content: this.pending,
      editable: !this.disabled(),
      editorProps: {
        attributes: {
          class: 'rich-text editable',
          role: 'textbox',
          'aria-multiline': 'true',
          ...(this.labelledBy() ? { 'aria-labelledby': this.labelledBy()! } : {}),
          ...(this.describedBy() ? { 'aria-describedby': this.describedBy()! } : {}),
          ...(this.placeholder() ? { 'data-placeholder': this.placeholder() } : {}),
        },
      },
      onUpdate: ({ editor }) => this.onChange(editor.isEmpty ? '' : editor.getHTML()),
      onTransaction: () => this.tick.update((n) => n + 1),
      onBlur: () => this.onTouched(),
    });
  }

  ngOnDestroy(): void {
    this.editor?.destroy();
  }

  // ControlValueAccessor

  writeValue(value: string | null): void {
    this.pending = value ?? '';
    this.editor?.commands.setContent(this.pending, { emitUpdate: false });
  }

  registerOnChange(fn: (value: string) => void): void {
    this.onChange = fn;
  }

  registerOnTouched(fn: () => void): void {
    this.onTouched = fn;
  }

  setDisabledState(disabled: boolean): void {
    this.disabled.set(disabled);
    this.editor?.setEditable(!disabled);
  }

  // Toolbar

  protected isActive(name: string, attributes?: Record<string, unknown>): boolean {
    this.tick();
    return this.editor?.isActive(name, attributes) ?? false;
  }

  protected canUndo(): boolean {
    this.tick();
    return this.editor?.can().undo() ?? false;
  }

  protected canRedo(): boolean {
    this.tick();
    return this.editor?.can().redo() ?? false;
  }

  protected run(command: string): void {
    const chain = this.editor?.chain().focus();
    if (!chain) {
      return;
    }
    switch (command) {
      case 'bold':
        chain.toggleBold().run();
        break;
      case 'italic':
        chain.toggleItalic().run();
        break;
      case 'underline':
        chain.toggleUnderline().run();
        break;
      case 'strike':
        chain.toggleStrike().run();
        break;
      case 'h2':
        chain.toggleHeading({ level: 2 }).run();
        break;
      case 'h3':
        chain.toggleHeading({ level: 3 }).run();
        break;
      case 'bulletList':
        chain.toggleBulletList().run();
        break;
      case 'orderedList':
        chain.toggleOrderedList().run();
        break;
      case 'blockquote':
        chain.toggleBlockquote().run();
        break;
      case 'undo':
        chain.undo().run();
        break;
      case 'redo':
        chain.redo().run();
        break;
    }
  }

  protected onKeydown(event: KeyboardEvent): void {
    // Ctrl+K: link, as in most editors
    if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 'k') {
      event.preventDefault();
      this.openLink();
    }
  }

  // Links

  protected openLink(): void {
    const editor = this.editor;
    if (!editor || this.disabled()) {
      return;
    }
    const attributes = editor.getAttributes('link');
    const editing = editor.isActive('link');
    this.linkEditing.set(editing);
    this.linkHref.set((attributes['href'] as string | undefined) ?? '');
    this.linkStyle.set(attributes['class'] === BUTTON_CLASS ? 'button' : 'text');
    this.linkNeedsText.set(!editing && editor.state.selection.empty);
    this.linkText.set('');
    this.linkError.set(null);
    this.linkOpen.set(true);
    afterNextRender(() => this.hrefField()?.nativeElement.focus(), { injector: this.injector });
  }

  protected applyLink(): void {
    const editor = this.editor;
    if (!editor) {
      return;
    }
    const href = normalizeLink(this.linkHref());
    if (!href) {
      this.linkError.set(
        'Adresse invalide : par exemple https://discord.gg/… ou une adresse e-mail.',
      );
      return;
    }
    const attributes = { href, class: this.linkStyle() === 'button' ? BUTTON_CLASS : null };
    const chain = editor.chain().focus();
    if (this.linkEditing()) {
      chain.extendMarkRange('link').setLink(attributes).run();
    } else if (!editor.state.selection.empty) {
      chain.setLink(attributes).run();
    } else {
      // Nothing selected: the link's text is inserted, then a space outside of it
      const text = this.linkText().trim() || href.replace(/^(https?:\/\/|mailto:)/, '');
      chain
        .insertContent([
          { type: 'text', text, marks: [{ type: 'link', attrs: attributes }] },
          { type: 'text', text: ' ' },
        ])
        .run();
    }
    this.linkOpen.set(false);
  }

  protected removeLink(): void {
    this.editor?.chain().focus().extendMarkRange('link').unsetLink().run();
    this.linkOpen.set(false);
  }

  protected cancelLink(): void {
    this.linkOpen.set(false);
    this.editor?.commands.focus();
  }

  protected onLinkKeydown(event: KeyboardEvent): void {
    if (event.key === 'Enter') {
      event.preventDefault();
      this.applyLink();
    } else if (event.key === 'Escape') {
      event.preventDefault();
      event.stopPropagation();
      this.cancelLink();
    }
  }
}
