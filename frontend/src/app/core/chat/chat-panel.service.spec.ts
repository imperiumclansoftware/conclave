import { TestBed } from '@angular/core/testing';
import { ActivatedRouteSnapshot, Router, convertToParamMap, provideRouter } from '@angular/router';
import { openChatPanel } from '../auth/auth.guards';
import { ChatPanelService } from './chat-panel.service';

describe('ChatPanelService', () => {
  beforeEach(() => {
    localStorage.clear();
    TestBed.configureTestingModule({ providers: [provideRouter([])] });
  });

  afterEach(() => localStorage.clear());

  it('opens on a conversation, goes back to the list, and remembers whether it is open', () => {
    const panel = TestBed.inject(ChatPanelService);
    expect(panel.isOpen()).toBe(false);

    panel.open(7);
    expect([panel.isOpen(), panel.contactId()]).toEqual([true, 7]);
    expect(localStorage.getItem('side-panel')).toBe('chat');

    panel.showList();
    expect([panel.isOpen(), panel.contactId()]).toEqual([true, null]);

    panel.toggle();
    expect(panel.isOpen()).toBe(false);

    // Remembered on the next visit
    panel.open();
    TestBed.resetTestingModule();
    TestBed.configureTestingModule({});
    expect(TestBed.inject(ChatPanelService).isOpen()).toBe(true);
  });

  it('is closed and back on the list after logout', () => {
    const panel = TestBed.inject(ChatPanelService);
    panel.open(3);

    panel.reset();

    expect([panel.isOpen(), panel.contactId()]).toEqual([false, null]);
  });

  it('turns former /messages/:memberId links into the sidebar over the clan page', () => {
    const route = { paramMap: convertToParamMap({ memberId: '5' }) } as ActivatedRouteSnapshot;

    const result = TestBed.runInInjectionContext(() => openChatPanel(route, {} as never));

    expect(TestBed.inject(Router).serializeUrl(result as never)).toBe('/clan');
    const panel = TestBed.inject(ChatPanelService);
    expect([panel.isOpen(), panel.contactId()]).toEqual([true, 5]);
  });
});
