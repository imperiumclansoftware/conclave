import { plainText } from '../../shared/rich-text-editor/rich-text';

export { plainText };

const dateFormat = new Intl.DateTimeFormat('fr-FR', {
  day: 'numeric',
  month: 'long',
  year: 'numeric',
});

export function newsDate(iso: string): string {
  return dateFormat.format(new Date(iso));
}

/** Start of the text (HTML of the editor or plain text), cut on a word */
export function excerpt(body: string, length = 180): string {
  const text = plainText(body).replace(/\s+/g, ' ').trim();
  if (text.length <= length) {
    return text;
  }
  const cut = text.slice(0, length);
  return (
    cut.slice(0, Math.max(cut.lastIndexOf(' '), length - 30)).replace(/[\s,;:.!?-]+$/, '') + '…'
  );
}
