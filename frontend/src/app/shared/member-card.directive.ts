import { Directive, ElementRef, inject, input } from '@angular/core';
import { MemberCardService } from '../core/members/member-card.service';

/**
 * Shows the member card when the mouse rests on a member's name. Touch screens and keyboards keep
 * the plain link to the profile, which holds the same information.
 */
@Directive({
  selector: '[appMemberCard]',
  host: {
    '(pointerenter)': 'enter($event)',
    '(pointerleave)': 'cards.hideSoon()',
    '(click)': 'cards.hide()',
  },
})
export class MemberCardDirective {
  protected readonly cards = inject(MemberCardService);
  private readonly element = inject(ElementRef<HTMLElement>);

  /** Member whose card is shown */
  readonly appMemberCard = input.required<number>();

  protected enter(event: PointerEvent): void {
    if (event.pointerType === 'mouse') {
      this.cards.show(this.appMemberCard(), this.element.nativeElement);
    }
  }
}
