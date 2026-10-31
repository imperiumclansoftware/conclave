/** How a link shows in the text: underlined words, or a button in the site's style */
export type LinkStyle = 'text' | 'button';

/** The class the server keeps on a link shown as a button (RichText::BUTTON_CLASS) */
export const BUTTON_CLASS = 'button';

/**
 * The address typed for a link, made usable: "discord.gg/abc" gets https://, an e-mail address
 * mailto:, a page of Conclave ("/evenements/4") stays as it is. Any other scheme (javascript:…)
 * is refused: null.
 */
export function normalizeLink(value: string): string | null {
  const text = value.trim();
  // "//host": neither a page of Conclave nor a complete address
  if (!text || /\s/.test(text) || text.startsWith('//')) {
    return null;
  }
  if (/^\/(?![/\\])/.test(text)) {
    return text;
  }
  if (/^mailto:[^\s@]+@[^\s@]+\.[^\s@]+$/i.test(text)) {
    return text;
  }
  if (/^[^\s@/:]+@[^\s@/]+\.[^\s@/]+$/.test(text)) {
    return `mailto:${text}`;
  }
  const candidate = /^https?:\/\//i.test(text)
    ? text
    : /^[a-z][a-z0-9+.-]*:/i.test(text)
      ? null
      : `https://${text}`;
  if (!candidate) {
    return null;
  }
  try {
    const url = new URL(candidate);
    return url.hostname.includes('.') || url.hostname === 'localhost' ? url.href : null;
  } catch {
    return null;
  }
}
