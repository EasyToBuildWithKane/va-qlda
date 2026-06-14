import { Node, mergeAttributes } from '@tiptap/core';

/** Block embed for Google Docs / Sheets preview (projects document pane pattern). */
export const GoogleWorkspaceEmbed = Node.create({
    name: 'googleWorkspaceEmbed',
    group: 'block',
    atom: true,
    selectable: true,
    draggable: true,

    addAttributes() {
        return {
            embedUrl: { default: null },
            viewUrl: { default: null },
            workspaceType: { default: 'spreadsheet' },
            label: { default: 'Google Sheets' },
        };
    },

    parseHTML() {
        return [
            {
                tag: 'div[data-google-workspace-embed]',
                getAttrs: (dom) => ({
                    embedUrl: dom.getAttribute('data-embed-url'),
                    viewUrl: dom.getAttribute('data-view-url'),
                    workspaceType: dom.getAttribute('data-workspace-type') || 'spreadsheet',
                    label: dom.getAttribute('data-label') || 'Google Sheets',
                }),
            },
        ];
    },

    renderHTML({ node }) {
        const { embedUrl, viewUrl, workspaceType, label } = node.attrs;
        return [
            'div',
            mergeAttributes({
                'data-google-workspace-embed': '',
                'data-embed-url': embedUrl,
                'data-view-url': viewUrl,
                'data-workspace-type': workspaceType,
                'data-label': label,
                class: 'google-workspace-embed coaching-rich-embed',
            }),
            [
                'div',
                { class: 'coaching-rich-embed__frame' },
                [
                    'iframe',
                    {
                        src: embedUrl,
                        title: label,
                        loading: 'lazy',
                        referrerpolicy: 'no-referrer-when-downgrade',
                        allow: 'clipboard-read; clipboard-write',
                    },
                ],
            ],
            [
                'a',
                {
                    href: viewUrl,
                    target: '_blank',
                    rel: 'noopener noreferrer',
                    class: 'coaching-rich-embed__link',
                },
                `Mở ${label}`,
            ],
        ];
    },
});
