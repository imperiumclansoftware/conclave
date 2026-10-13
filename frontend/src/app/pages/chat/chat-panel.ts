import { Component, inject, viewChild } from '@angular/core';
import { RouterLink } from '@angular/router';
import { ChatPanelService } from '../../core/chat/chat-panel.service';
import { ChatStore } from '../../core/chat/chat.store';
import { CallsService } from '../../core/discord/calls.service';
import { DiscordService } from '../../core/discord/discord.service';
import { Avatar } from '../../shared/avatar';
import { CallDialog } from '../../shared/call-dialog';
import { Icon } from '../../shared/icon';
import { PresenceBadge } from '../../shared/presence-badge';
import { Thread } from './thread';

/**
 * Messaging sidebar: the list of conversations, or the conversation with one member,
 * plus the ongoing video calls.
 */
@Component({
  selector: 'app-chat-panel',
  imports: [RouterLink, PresenceBadge, CallDialog, Avatar, Icon, Thread],
  templateUrl: './chat-panel.html',
  styleUrl: './chat-panel.scss',
})
export class ChatPanel {
  protected readonly chat = inject(ChatStore);
  protected readonly calls = inject(CallsService);
  protected readonly discord = inject(DiscordService);
  protected readonly panel = inject(ChatPanelService);
  private readonly callDialog = viewChild.required(CallDialog);

  protected startCall(memberIds: number[] = []): void {
    this.callDialog().open(memberIds);
  }

  protected async endCall(callId: number): Promise<void> {
    if (confirm('Mettre fin à cette visio ? Le salon Discord sera supprimé.')) {
      await this.calls.end(callId);
    }
  }
}
