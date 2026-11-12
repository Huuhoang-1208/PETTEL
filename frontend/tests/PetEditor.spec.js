import { describe, expect, it } from 'vitest';
import { mount } from '@vue/test-utils';
import PetEditor from '../src/features/owner/pets/components/PetEditor.vue';

describe('PetEditor', () => {
  it('emits only editable pet fields and retains a server validation error', async () => {
    const wrapper = mount(PetEditor, { props: { error: 'Tên thú cưng đã vượt giới hạn.' } });
    await wrapper.get('input').setValue('Mochi');
    await wrapper.get('form').trigger('submit');
    expect(wrapper.emitted('save')[0][0]).toMatchObject({ name: 'Mochi', species: 'DOG', size: null });
    expect(wrapper.get('[role="alert"]').text()).toContain('vượt giới hạn');
  });
  it('uses the existing pet values while editing', () => {
    const wrapper = mount(PetEditor, { props: { pet: { id: '3', name: 'Miu', species: 'CAT', size: 'SMALL' } } });
    expect(wrapper.get('input').element.value).toBe('Miu');
    expect(wrapper.get('h2').text()).toBe('Chỉnh sửa hồ sơ');
  });
});
