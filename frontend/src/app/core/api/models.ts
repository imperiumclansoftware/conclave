export type ClanRole = 'leader' | 'member';

export interface Member {
  id: number;
  username: string;
  displayName: string;
  role: ClanRole;
  /** Relative to the app base URL, or absolute (Discord); null: show initials */
  avatarUrl?: string | null;
}

export interface Me extends Member {
  clan: { id: number; name: string };
  email: string | null;
  /** Theme chosen for the account; null: follow the device's light/dark setting */
  faction: 'assassin' | 'templar' | null;
  /** Member of a branch allowed to publish the news (the Communicants) */
  canPublishNews: boolean;
  /** Member of the Joueurs branch: the games part is theirs */
  canAccessGames: boolean;
}

export interface Message {
  id: number;
  conversationId: number;
  senderId: number;
  content: string;
  createdAt: string;
}

export interface Conversation {
  id: number;
  participants: Member[];
  lastMessage: Message | null;
  lastReadMessageId: number | null;
  unreadCount: number;
}

export interface AuthResponse {
  token: string;
  user?: Me;
}

/** Sign-up result: the founder is logged in, the others wait for a leader's approval. */
/** Signing up: the account waits for a leader's approval */
export type RegisterResponse = { status: 'pending' };

/** Installation wizard: the clan and its first leader */
export interface SetupPayload {
  code: string;
  clanName: string;
  appUrl: string | null;
  username: string;
  displayName: string;
  email: string;
  password: string;
}

export interface SetupStatus {
  initialized: boolean;
  clanName: string | null;
}

/** Problem details returned by Symfony on errors. */
export interface ApiProblem {
  detail?: string;
  message?: string;
  violations?: { propertyPath: string; title: string }[];
}

export type GamePlatform =
  'pc' | 'ps5' | 'ps4' | 'xbox_series' | 'xbox_one' | 'switch_2' | 'switch' | 'mobile';

export interface PlatformOption {
  value: GamePlatform;
  label: string;
}

export interface Game {
  id: number;
  source: string;
  externalId: string;
  name: string;
  coverUrl: string | null;
  releaseDate: string | null;
  genres: string[];
  availablePlatforms: GamePlatform[];
}

export interface Screenshot {
  thumbnail: string;
  full: string;
}

/** A game played by the current user. */
export interface MyGame {
  id: number;
  game: Game;
  platforms: GamePlatform[];
  addedAt: string;
}

export interface Player {
  user: Member;
  platforms: GamePlatform[];
}

export interface GameDetails extends Game {
  description: string | null;
  screenshots: Screenshot[];
  developers: string[];
  website: string | null;
  players: Player[];
  mine: MyGame | null;
}

/** A search result from the external catalog. */
export interface CatalogGame {
  source: string;
  externalId: string;
  name: string;
  coverUrl: string | null;
  releaseDate: string | null;
  platforms: GamePlatform[];
  inLibrary: boolean;
}

export interface ClanGame {
  game: Game;
  players: Player[];
}

export type AppStatus = 'online' | 'away' | 'offline';
export type DiscordStatus = 'online' | 'idle' | 'dnd' | 'offline';

export interface Presence {
  userId: number;
  app: { status: AppStatus; ageSeconds: number | null };
  discord: {
    username: string;
    avatarUrl: string | null;
    status: DiscordStatus;
    activity: string | null;
  } | null;
}

export interface VideoCall {
  id: number;
  title: string;
  joinUrl: string;
  createdBy: Member | null;
  participants: Member[];
  connectedUserIds: number[];
  started: boolean;
  createdAt: string;
}

export interface DiscordIntegration {
  enabled: boolean;
  account: {
    id: string;
    username: string;
    avatarUrl: string | null;
    onServer: boolean | null;
  } | null;
  guild: { id: string; name: string } | null;
}

export type GamerNetwork = 'psn' | 'xbox' | 'steam' | 'nintendo' | 'epic' | 'battlenet';

export type AvatarSource = 'upload' | 'gravatar' | 'discord' | 'initials';

export interface AvatarSettings {
  /** Source actually shown */
  source: AvatarSource;
  /** Source chosen by the member (null: first available) */
  chosen: AvatarSource | null;
  /** URL of each configured source */
  available: Partial<Record<Exclude<AvatarSource, 'initials'>, string>>;
}

export interface Profile extends Member {
  bio: string | null;
  /** Only in the member's own profile */
  email?: string | null;
  /** Only in the member's own profile */
  avatar?: AvatarSettings;
  gamerTags: Partial<Record<GamerNetwork, string>>;
  discordUsername: string | null;
  memberSince: string;
  networks: { value: GamerNetwork; label: string }[];
  /** Branches of the clan the member belongs to, by name */
  branches: BranchRef[];
}

/** Coats of arms drawn by the app (see BranchCrest component) */
export type BranchCrest = 'players' | 'developers' | 'communicants' | 'cinephiles';

export interface BranchRef {
  id: number;
  name: string;
  /** null: plain banner */
  crest: BranchCrest | null;
}

/** Part of the clan set up by the leaders, joined freely by the members */
export interface Branch extends BranchRef {
  description: string | null;
  /** Its members can publish the clan's news */
  publishesNews: boolean;
  /** Joining needs a leader's approval */
  approvalRequired: boolean;
  /** Members waiting for that approval */
  pendingIds: number[];
  /** Inactive: only leaders see it, in the administration */
  active: boolean;
  memberIds: number[];
}

export interface MemberProfile extends Profile {
  /** null: the games are reserved to the Joueurs branch, which I am not in */
  games: MyGame[] | null;
}

export interface SettingValue {
  value: string | null;
  source: 'admin' | 'env' | null;
}

export interface SecretSetting {
  set: boolean;
  hint: string | null;
  source: 'admin' | 'env' | null;
}

export interface GatewayState {
  state: 'stopped' | 'connecting' | 'connected' | 'waiting-config' | 'error';
  detail: string | null;
  botName: string | null;
  guilds: number | null;
  at: number | null;
}

export type MailMode = 'smtp' | 'sendmail' | 'disabled' | 'custom';
export type MailSecurity = 'starttls' | 'tls' | 'none';

export interface MailServer {
  mode: MailMode;
  host: string | null;
  port: number | null;
  security: MailSecurity;
  username: string | null;
  passwordSet: boolean;
  verifyPeer: boolean;
  /** Only a hint of a custom DSN, which may contain secrets */
  customDsn: { set: boolean; hint: string } | null;
}

export interface MailSettings {
  configured: boolean;
  /** Where the server comes from */
  source: 'admin' | 'env' | null;
  /** MAILER_DSN is set in .env: the administration can go back to it */
  envServer: boolean;
  server: MailServer;
  from: SettingValue;
  fromName: SettingValue;
}

/** Mail service as edited in the administration; null secrets keep the saved ones. */
export interface MailSettingsInput {
  mode: MailMode;
  host: string | null;
  port: number | null;
  security: MailSecurity;
  username: string | null;
  password: string | null;
  verifyPeer: boolean;
  dsn: string | null;
  from: string | null;
  fromName: string | null;
}

export interface AdminSettings {
  general: { clanName: string | null; appUrl: SettingValue };
  mail: MailSettings;
  discord: {
    enabled: boolean;
    clientId: SettingValue;
    clientSecret: SecretSetting;
    botToken: SecretSetting;
    redirectUri: SettingValue;
    gateway: GatewayState;
  };
  rawg: { apiKey: SecretSetting };
}

export interface CheckResult {
  ok: boolean;
  checks: { label: string; ok: boolean; detail: string | null }[];
}

/** Account as seen by leaders in the administration. */
export interface AdminMember extends Member {
  email: string | null;
  status: 'pending' | 'active';
  createdAt: string;
}

/** A member asks to join a branch (leaders' administration) */
export interface BranchJoinRequest {
  id: number;
  branch: BranchRef;
  member: Member;
  createdAt: string;
}

/** News of the clan */
export interface News {
  id: number;
  title: string;
  /** Plain text, paragraphs separated by blank lines */
  body: string;
  /** null: default visual (NewsCover) */
  imageUrl: string | null;
  /** null once the author has left the clan */
  author: Member | null;
  createdAt: string;
  updatedAt: string | null;
  canEdit: boolean;
  canDelete: boolean;
}

export interface NewsPage {
  items: News[];
  hasMore: boolean;
}

/** Summary shown when hovering a member's name */
export interface MemberCard extends Member {
  memberSince: string;
  discordUsername: string | null;
  /** Active branches, by name */
  branches: BranchRef[];
  /** games: null outside of the Joueurs branch */
  stats: { games: number | null; news: number; branches: number };
}

/** Favourite link of a branch (links sidebar) */
export interface BranchLink {
  id: number;
  title: string;
  url: string;
  host: string;
  /** null once the member has left the clan */
  addedBy: { id: number; displayName: string } | null;
  /** Added by me, or I am a leader */
  canRemove: boolean;
}

/** Links of one of my branches */
export interface BranchLinks {
  branch: BranchRef;
  links: BranchLink[];
}
