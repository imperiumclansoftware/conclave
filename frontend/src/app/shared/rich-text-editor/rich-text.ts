import { AbstractControl, ValidationErrors, ValidatorFn } from '@angular/forms';

/**
 * The words of a text written with the editor (HTML), paragraphs on separate lines. A text without
 * markup is returned as it is.
 */
export function plainText(html: string): string {
  if (!html.includes('<')) {
    return html;
  }
  const marked = html.replace(/<br\s*\/?>|<\/(p|h2|h3|li|blockquote)>/gi, '$&\n');
  // Parsed, never inserted in the page: no script can run
  const text = new DOMParser().parseFromString(marked, 'text/html').body.textContent ?? '';
  return text.replace(/\n\s*\n+/g, '\n\n').trim();
}

/**
 * Length of the text written with the editor, formatting left out, as the server counts it:
 * errors "required" (nothing written) and "maxlength".
 */
export function richTextValidator(options: { max: number; required?: boolean }): ValidatorFn {
  return (control: AbstractControl<string | null>): ValidationErrors | null => {
    const length = plainText(control.value ?? '').trim().length;
    if (length === 0) {
      return options.required ? { required: true } : null;
    }
    return length > options.max
      ? { maxlength: { requiredLength: options.max, actualLength: length } }
      : null;
  };
}
