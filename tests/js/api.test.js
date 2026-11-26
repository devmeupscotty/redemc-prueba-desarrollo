import { describe, expect, it, vi } from 'vitest';
import { $ } from './support.js';
import { createApi } from '../../public/js/api.js';

const ENDPOINT = '/api/persons';

describe('createApi', () => {
    it('aborts the previous list request when a new page is requested', () => {
        const first = { abort: vi.fn() };
        const ajax = vi.spyOn($, 'ajax').mockReturnValueOnce(first).mockReturnValueOnce({ abort: vi.fn() });
        const api = createApi(ENDPOINT);

        api.list(1);
        api.list(2);

        expect(first.abort).toHaveBeenCalledOnce();
        expect(ajax).toHaveBeenLastCalledWith(expect.objectContaining({ url: ENDPOINT, data: { page: 2 } }));
        ajax.mockRestore();
    });

    it('posts new people to the endpoint', () => {
        const ajax = vi.spyOn($, 'ajax').mockReturnValue({});

        createApi(ENDPOINT).create({ name: 'Ana' });

        expect(ajax).toHaveBeenCalledWith(expect.objectContaining({ url: ENDPOINT, method: 'POST', data: { name: 'Ana' } }));
        ajax.mockRestore();
    });
});
