const dateFormat = new Intl.DateTimeFormat('fr-FR', {
  day: 'numeric',
  month: 'long',
  year: 'numeric',
});

export function newsDate(iso: string): string {
  return dateFormat.format(new Date(iso));
}

/** Start of the text, cut on a word */
export function excerpt(body: string, length = 180): string {
  const text = body.replace(/\s+/g, ' ').trim();
  if (text.length <= length) {
    return text;
  }
  const cut = text.slice(0, length);
  return (
    cut.slice(0, Math.max(cut.lastIndexOf(' '), length - 30)).replace(/[\s,;:.!?-]+$/, '') + '…'
  );
}

/** Paragraphs separated by blank lines; single line breaks are kept in each one */
export function paragraphs(body: string): string[] {
  return body
    .split(/\n{2,}/)
    .map((p) => p.trim())
    .filter(Boolean);
}
