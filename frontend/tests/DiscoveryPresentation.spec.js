import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import DiscoveryFilters from '../src/features/owner/discovery/components/DiscoveryFilters.vue';
import ListingCard from '../src/features/owner/discovery/components/ListingCard.vue';
import ServiceHero from '../src/features/owner/discovery/components/ServiceHero.vue';

const RouterLinkStub = {
  props: ['to'],
  template: '<a :href="typeof to === \'string\' ? to : \'#\'"><slot /></a>',
};

describe('Discovery presentation', () => {
  it('renders the approved hero copy and imported hero artwork', () => {
    const wrapper = mount(ServiceHero);
    expect(wrapper.get('h1').text()).toContain('Thêm an tâm cho');
    expect(wrapper.get('.hero-art img').attributes('src')).toContain('heroImage');
    expect(wrapper.get('.hero-cta').attributes('href')).toBe('#results');
  });

  it('uses prototype species artwork when the API listing has no media', () => {
    const wrapper = mount(ListingCard, {
      props: {
        listing: {
          id: 'listing-1',
          title: 'Chăm mèo tại nhà',
          service_type: 'DAYCARE',
          area_code: 'DN_CENTER',
          acceptance_rules_json: { species: ['CAT'], sizes: ['SMALL'] },
          price_vnd: 50000,
          price_unit: 'HOUR',
          review_count: 0,
        },
      },
      global: { stubs: { RouterLink: RouterLinkStub } },
    });
    expect(decodeURIComponent(wrapper.get('.listing-photo img').attributes('src'))).toContain('chú mèo');
    expect(wrapper.text()).toContain('Hải Châu, Đà Nẵng');
    expect(wrapper.text()).toContain('Chưa có đánh giá');
    expect(wrapper.text()).toContain('50.000');
  });

  it('emits user-entered search values and submits without mutating props', async () => {
    const model = {
      q: '', service_type: '', area_code: '', pet_id: '', smart_match: false,
      start_at: '', end_at: '', min_price_vnd: '', max_price_vnd: '',
    };
    const wrapper = mount(DiscoveryFilters, {
      props: { model, pets: [], authenticated: false },
      global: { stubs: { RouterLink: RouterLinkStub } },
    });
    await wrapper.get('#q').setValue('chăm chó');
    await wrapper.get('#search-form').trigger('submit');
    expect(wrapper.emitted('update:filter')).toContainEqual([{ field: 'q', value: 'chăm chó' }]);
    expect(wrapper.emitted('search')).toHaveLength(1);
    expect(model.q).toBe('');
  });
});
