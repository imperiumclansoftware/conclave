import { EventMessage } from '../../core/api/models';
import { dayLabel } from './event-format';

/** Messages of one author closer than this form a group: name and avatar shown once */
const GROUP_MS = 5 * 60_000;

export interface ChatItem {
  message: EventMessage;
  mine: boolean;
  /** First message of a group: show who writes */
  showAuthor: boolean;
  /** First message of a day: its label */
  day: string | null;
}

/** The messages as shown in the room, with day separators and author groups */
export function chatItems(messages: EventMessage[], myId: number | null): ChatItem[] {
  return messages.map((message, i) => {
    const previous = messages[i - 1];
    const day = dayLabel(message.createdAt);
    const newDay = !previous || dayLabel(previous.createdAt) !== day;
    const sameAuthor =
      !!previous &&
      previous.author?.id === message.author?.id &&
      Date.parse(message.createdAt) - Date.parse(previous.createdAt) < GROUP_MS;
    return {
      message,
      mine: message.author !== null && message.author.id === myId,
      showAuthor: newDay || !sameAuthor,
      day: newDay ? day : null,
    };
  });
}

/** Adds a message received in real time (or just sent), once and in order */
export function withMessage(messages: EventMessage[], message: EventMessage): EventMessage[] {
  if (messages.some((m) => m.id === message.id)) {
    return messages;
  }
  return [...messages, message].sort((a, b) => a.id - b.id);
}
