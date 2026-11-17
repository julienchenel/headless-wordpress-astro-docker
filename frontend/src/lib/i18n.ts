/**
 * Multilingue : langue d'une page, liens hreflang, sélecteur de langue et textes de l'interface.
 * Les langues et les URLs viennent de Polylang (via WPGraphQL Polylang).
 */
import type { WpLanguage, WpLanguages, WpNode } from './wordpress';

/** Lien vers la même page dans une langue (hreflang + sélecteur). */
export interface Alternate {
  slug: string;
  name: string;
  /** Valeur pour `lang` / `hreflang` (fr-FR, en-GB…). */
  hreflang: string;
  href: string;
  current: boolean;
  /** true si la page existe dans cette langue (sinon le lien mène à l'accueil de la langue). */
  translated: boolean;
}

/** Textes de l'interface. Ajouter une entrée par nouvelle langue Polylang. */
const UI = {
  fr: {
    latestPosts: 'Derniers articles',
    home: 'Accueil',
    languages: 'Langues',
    preview: 'Preview — brouillon non publié',
    noPosts: 'Aucun article pour le moment.',
  },
  en: {
    latestPosts: 'Latest posts',
    home: 'Home',
    languages: 'Languages',
    preview: 'Preview — unpublished draft',
    noPosts: 'No posts yet.',
  },
} as const;

export type UiStrings = (typeof UI)[keyof typeof UI];

export function t(languageSlug: string | undefined): UiStrings {
  return UI[(languageSlug ?? '') as keyof typeof UI] ?? UI.fr;
}

/** fr_FR → fr-FR (format BCP 47 attendu par lang et hreflang). */
export function toHreflang(language: WpLanguage | null | undefined): string {
  return language ? language.locale.replace('_', '-') : 'fr';
}

/** Langue d'un contenu (langue par défaut si le contenu n'en a pas). */
export function languageOf(node: WpNode, { languages, defaultLanguage }: WpLanguages): WpLanguage | null {
  return languages.find((l) => l.slug === node.language?.slug) ?? defaultLanguage;
}

/** Équivalents d'un contenu dans chaque langue (sa traduction, sinon l'accueil de la langue). */
export function alternatesForNode(node: WpNode, { languages, defaultLanguage }: WpLanguages): Alternate[] {
  const current = languages.find((l) => l.slug === node.language?.slug) ?? defaultLanguage;
  return languages.map((language) => {
    const translation = node.translations?.find((tr) => tr.language?.slug === language.slug);
    const isCurrent = language.slug === current?.slug;
    return {
      slug: language.slug,
      name: language.name,
      hreflang: toHreflang(language),
      href: isCurrent ? node.uri : (translation?.uri ?? language.homeUri),
      current: isCurrent,
      translated: isCurrent || Boolean(translation),
    };
  });
}

/** Équivalents de l'accueil d'une langue. */
export function alternatesForHome(current: WpLanguage | null, { languages }: WpLanguages): Alternate[] {
  return languages.map((language) => ({
    slug: language.slug,
    name: language.name,
    hreflang: toHreflang(language),
    href: language.homeUri,
    current: language.slug === current?.slug,
    translated: true,
  }));
}
