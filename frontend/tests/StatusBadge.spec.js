import { describe, expect, it } from 'vitest';
import { mount } from '@vue/test-utils';
import StatusBadge from '../src/shared/components/StatusBadge.vue';

describe('StatusBadge', () => {
  it('renders a localized booking state label', () => {
    const wrapper = mount(StatusBadge, { props: { status: 'AWAITING_CONFIRMATION' } });
    expect(wrapper.text()).toBe('Chờ bạn xác nhận');
  });
});
