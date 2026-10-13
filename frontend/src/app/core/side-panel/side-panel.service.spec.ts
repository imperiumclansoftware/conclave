import { TestBed } from '@angular/core/testing';
import { SidePanelService } from './side-panel.service';

describe('SidePanelService', () => {
  beforeEach(() => localStorage.clear());
  afterEach(() => localStorage.clear());

  it('opens one sidebar at a time and remembers it', () => {
    const panels = TestBed.inject(SidePanelService);

    panels.open('chat');
    panels.toggle('links');
    expect(panels.active()).toBe('links');

    panels.close('chat');
    expect(panels.active()).toBe('links');
    panels.toggle('links');
    expect(panels.active()).toBeNull();

    panels.open('links');
    TestBed.resetTestingModule();
    expect(TestBed.inject(SidePanelService).active()).toBe('links');
  });

  it('keeps the messaging sidebar open for members who had it open before', () => {
    localStorage.setItem('chat-panel-open', '1');

    expect(TestBed.inject(SidePanelService).active()).toBe('chat');
  });
});
