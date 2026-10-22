<?php

if (! function_exists('icon')) {
    /**
     * Inline SVG icon (Lucide icon set, ISC license). Decorative by default:
     * pair it with visible text or an aria-label on the parent.
     */
    function icon(string $name, string $class = ''): string
    {
        static $paths = [
            'dashboard'      => '<rect width="7" height="9" x="3" y="3" rx="1"/><rect width="7" height="5" x="14" y="3" rx="1"/><rect width="7" height="9" x="14" y="12" rx="1"/><rect width="7" height="5" x="3" y="16" rx="1"/>',
            'users'          => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>',
            'user-plus'      => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M19 8v6"/><path d="M22 11h-6"/>',
            'history'        => '<path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"/><path d="M3 3v5h5"/><path d="M12 7v5l4 2"/>',
            'classes'        => '<path d="M22 10v6"/><path d="M2 10l10-5 10 5-10 5z"/><path d="M6 12v5c3 3 9 3 12 0v-5"/>',
            'rubrics'        => '<path d="m3 17 2 2 4-4"/><path d="m3 7 2 2 4-4"/><path d="M13 6h8"/><path d="M13 12h8"/><path d="M13 18h8"/>',
            'file-text'      => '<path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z"/><path d="M14 2v4a2 2 0 0 0 2 2h4"/><path d="M10 9H8"/><path d="M16 13H8"/><path d="M16 17H8"/>',
            'inbox'          => '<path d="M22 12h-6l-2 3h-4l-2-3H2"/><path d="M5.45 5.11 2 12v6a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-6l-3.45-6.89A2 2 0 0 0 16.76 4H7.24a2 2 0 0 0-1.79 1.11z"/>',
            'log-out'        => '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><path d="m16 17 5-5-5-5"/><path d="M21 12H9"/>',
            'key'            => '<circle cx="7.5" cy="15.5" r="5.5"/><path d="m21 2-9.6 9.6"/><path d="m15.5 7.5 3 3L22 7l-3-3"/>',
            'plus'           => '<path d="M5 12h14"/><path d="M12 5v14"/>',
            'menu'           => '<path d="M4 6h16"/><path d="M4 12h16"/><path d="M4 18h16"/>',
            'x'              => '<path d="M18 6 6 18"/><path d="m6 6 12 12"/>',
            'chevron-down'   => '<path d="m6 9 6 6 6-6"/>',
            'chevron-right'  => '<path d="m9 18 6-6-6-6"/>',
            'arrow-right'    => '<path d="M5 12h14"/><path d="m12 5 7 7-7 7"/>',
            'pencil'         => '<path d="M17 3a2.85 2.83 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5Z"/>',
            'copy'           => '<rect width="14" height="14" x="8" y="8" rx="2"/><path d="M4 16c-1.1 0-2-.9-2-2V4c0-1.1.9-2 2-2h10c1.1 0 2 .9 2 2"/>',
            'archive'        => '<rect width="20" height="5" x="2" y="3" rx="1"/><path d="M4 8v11a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8"/><path d="M10 12h4"/>',
            'trash'          => '<path d="M3 6h18"/><path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"/><path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"/>',
            'calendar'       => '<rect width="18" height="18" x="3" y="4" rx="2"/><path d="M16 2v4"/><path d="M8 2v4"/><path d="M3 10h18"/>',
            'clock'          => '<circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/>',
            'check-circle'   => '<circle cx="12" cy="12" r="10"/><path d="m9 12 2 2 4-4"/>',
            'alert-circle'   => '<circle cx="12" cy="12" r="10"/><path d="M12 8v4"/><path d="M12 16h.01"/>',
            'alert-triangle' => '<path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3"/><path d="M12 9v4"/><path d="M12 17h.01"/>',
            'send'           => '<path d="m22 2-7 20-4-9-9-4Z"/><path d="M22 2 11 13"/>',
            'search'         => '<circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/>',
            'shield'         => '<path d="M20 13c0 5-3.5 7.5-7.66 8.95a1 1 0 0 1-.67-.01C7.5 20.5 4 18 4 13V6a1 1 0 0 1 1-1c2 0 4.5-1.2 6.24-2.72a1.17 1.17 0 0 1 1.52 0C14.51 3.81 17 5 19 5a1 1 0 0 1 1 1z"/>',
            'sparkles'       => '<path d="m12 3-1.9 5.8a2 2 0 0 1-1.3 1.3L3 12l5.8 1.9a2 2 0 0 1 1.3 1.3L12 21l1.9-5.8a2 2 0 0 1 1.3-1.3L21 12l-5.8-1.9a2 2 0 0 1-1.3-1.3Z"/>',
            'bar-chart'      => '<path d="M3 3v18h18"/><path d="M18 17V9"/><path d="M13 17V5"/><path d="M8 17v-3"/>',
            'book-open'      => '<path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"/><path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"/>',
            'lock'           => '<rect width="18" height="11" x="3" y="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>',
            'graduation'     => '<path d="M21.42 10.92a1 1 0 0 0-.02-1.84L12.83 5.18a2 2 0 0 0-1.66 0L2.6 9.08a1 1 0 0 0 0 1.83l8.57 3.91a2 2 0 0 0 1.66 0z"/><path d="M22 10v6"/><path d="M6 12.5V16a6 3 0 0 0 12 0v-3.5"/>',
            'filter'         => '<path d="M22 3H2l8 9.46V19l4 2v-8.54L22 3z"/>',
            'mail'           => '<rect width="20" height="16" x="2" y="4" rx="2"/><path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"/>',
            'eye'            => '<path d="M2.06 12.35a1 1 0 0 1 0-.7 10.75 10.75 0 0 1 19.88 0 1 1 0 0 1 0 .7 10.75 10.75 0 0 1-19.88 0"/><circle cx="12" cy="12" r="3"/>',
            'eye-off'        => '<path d="M10.73 5.08A10.43 10.43 0 0 1 12 5c7 0 10 7 10 7a13.16 13.16 0 0 1-1.67 2.68"/><path d="M6.61 6.61A13.53 13.53 0 0 0 2 12s3 7 10 7a9.74 9.74 0 0 0 5.39-1.61"/><path d="M9.88 9.88a3 3 0 1 0 4.24 4.24"/><path d="m2 2 20 20"/>',
            'refresh'        => '<path d="M3 12a9 9 0 0 1 9-9 9.75 9.75 0 0 1 6.74 2.74L21 8"/><path d="M21 3v5h-5"/><path d="M21 12a9 9 0 0 1-9 9 9.75 9.75 0 0 1-6.74-2.74L3 16"/><path d="M8 16H3v5"/>',
            'shield-check'   => '<path d="M20 13c0 5-3.5 7.5-7.66 8.95a1 1 0 0 1-.67-.01C7.5 20.5 4 18 4 13V6a1 1 0 0 1 1-1c2 0 4.5-1.2 6.24-2.72a1.17 1.17 0 0 1 1.52 0C14.51 3.81 17 5 19 5a1 1 0 0 1 1 1z"/><path d="m9 12 2 2 4-4"/>',
            'settings'       =>'<path d="M12.22 2h-.44a2 2 0 0 0-2 2v.18a2 2 0 0 1-1 1.73l-.43.25a2 2 0 0 1-2 0l-.15-.08a2 2 0 0 0-2.73.73l-.22.38a2 2 0 0 0 .73 2.73l.15.1a2 2 0 0 1 1 1.72v.51a2 2 0 0 1-1 1.74l-.15.09a2 2 0 0 0-.73 2.73l.22.38a2 2 0 0 0 2.73.73l.15-.08a2 2 0 0 1 2 0l.43.25a2 2 0 0 1 1 1.73V20a2 2 0 0 0 2 2h.44a2 2 0 0 0 2-2v-.18a2 2 0 0 1 1-1.73l.43-.25a2 2 0 0 1 2 0l.15.08a2 2 0 0 0 2.73-.73l.22-.39a2 2 0 0 0-.73-2.73l-.15-.08a2 2 0 0 1-1-1.74v-.5a2 2 0 0 1 1-1.74l.15-.09a2 2 0 0 0 .73-2.73l-.22-.38a2 2 0 0 0-2.73-.73l-.15.08a2 2 0 0 1-2 0l-.43-.25a2 2 0 0 1-1-1.73V4a2 2 0 0 0-2-2z"/><circle cx="12" cy="12" r="3"/>',
            'upload'         => '<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><path d="m17 8-5-5-5 5"/><path d="M12 3v12"/>',
            'download'       => '<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><path d="m7 10 5 5 5-5"/><path d="M12 15V3"/>',
            'paperclip'      => '<path d="m21.44 11.05-9.19 9.19a6 6 0 0 1-8.49-8.49l8.57-8.57A4 4 0 1 1 18 8.84l-8.59 8.57a2 2 0 0 1-2.83-2.83l8.49-8.48"/>',
            'school'         => '<path d="M14 22v-4a2 2 0 1 0-4 0v4"/><path d="m18 10 4 2v8a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2v-8l4-2"/><path d="M18 5v17"/><path d="m4 6 8-4 8 4"/><path d="M6 5v17"/><circle cx="12" cy="9" r="2"/>',
        ];

        $svg = $paths[$name] ?? $paths['file-text'];

        return '<svg class="icon ' . esc($class, 'attr') . '" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"'
            . ' fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"'
            . ' aria-hidden="true" focusable="false">' . $svg . '</svg>';
    }
}

if (! function_exists('brand_logo')) {
    /**
     * The RubricMind logo (3D icon on a white tile). Decorative: the "RubricMind"
     * name is always written next to it.
     *
     * @param string $size '' (36px) or 'md' (44px)
     */
    function brand_logo(string $size = ''): string
    {
        $class = 'brand__logo' . ($size !== '' ? ' brand__logo--' . esc($size, 'attr') : '');

        return '<img class="' . $class . '" src="' . base_url('assets/img/logo-3d.jpg') . '" alt="" width="120" height="120">';
    }
}

if (! function_exists('password_input')) {
    /**
     * A password box with a lock icon and a show/hide button (handled by
     * data-toggle-password in assets/js/app.js). Put the <label> in the view.
     *
     * @param array<string, string|int|bool> $attributes Extra attributes, e.g. ['autocomplete' => 'new-password', 'required' => true].
     */
    function password_input(string $id, string $name, array $attributes = []): string
    {
        $extra = '';

        foreach ($attributes as $attribute => $value) {
            if ($value === true) {
                $extra .= ' ' . esc($attribute, 'attr');
            } elseif ($value !== false) {
                $extra .= ' ' . esc($attribute, 'attr') . '="' . esc((string) $value, 'attr') . '"';
            }
        }

        $id = esc($id, 'attr');

        return '<span class="input-icon input-icon--action">' . icon('lock')
            . '<input type="password" id="' . $id . '" name="' . esc($name, 'attr') . '"' . $extra . '>'
            . '<button type="button" class="input-icon__button" data-toggle-password="' . $id . '" aria-label="Show password" aria-pressed="false">'
            . '<span data-when="hidden">' . icon('eye-off') . '</span>'
            . '<span data-when="shown" hidden>' . icon('eye') . '</span>'
            . '</button></span>';
    }
}

if (! function_exists('is_modal_request')) {
    /**
     * True when the page is being loaded into the shared modal (see assets/js/app.js).
     */
    function is_modal_request(): bool
    {
        return service('request')->getHeaderLine(App\Filters\ModalFilter::HEADER) === '1';
    }
}

if (! function_exists('page_layout')) {
    /**
     * The layout for a form page: the bare modal layout inside a modal, the full app layout otherwise.
     */
    function page_layout(): string
    {
        return is_modal_request() ? 'layouts/modal' : 'layouts/app';
    }
}

if (! function_exists('cancel_button')) {
    /**
     * "Cancel" for a form: closes the modal, or links back on a full page.
     */
    function cancel_button(string $url, string $label = 'Cancel'): string
    {
        return is_modal_request()
            ? '<button type="button" class="button" data-close-modal>' . esc($label) . '</button>'
            : '<a href="' . esc($url, 'attr') . '" class="button">' . esc($label) . '</a>';
    }
}

if (! function_exists('initials')) {
    /**
     * Up to two initials for an avatar, e.g. "Maria Santos" -> "MS".
     */
    function initials(string $name): string
    {
        $parts = preg_split('/\s+/u', trim($name), -1, PREG_SPLIT_NO_EMPTY);

        if ($parts === []) {
            return '?';
        }

        $first = mb_substr($parts[0], 0, 1);
        $last  = count($parts) > 1 ? mb_substr(end($parts), 0, 1) : '';

        return mb_strtoupper($first . $last);
    }
}

if (! function_exists('avatar')) {
    /**
     * Circle with the user's initials. The color is stable per name.
     */
    function avatar(string $name, string $size = ''): string
    {
        $hue = crc32($name) % 6;

        return '<span class="avatar avatar--' . $hue . ($size !== '' ? ' avatar--' . esc($size, 'attr') : '') . '" aria-hidden="true">'
            . esc(initials($name)) . '</span>';
    }
}
