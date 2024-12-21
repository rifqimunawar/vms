/**
 * @license Copyright (c) 2003-2024, CKSource Holding sp. z o.o. All rights reserved.
 * For licensing, see LICENSE.md or https://ckeditor.com/legal/ckeditor-oss-license
 */
import { EditorUI, normalizeToolbarConfig, DialogView } from 'ckeditor5/src/ui.js';
import { enablePlaceholder } from 'ckeditor5/src/engine.js';
import { ElementReplacer, Rect } from 'ckeditor5/src/utils.js';
/**
 * The classic editor UI class.
 */
export default class ClassicEditorUI extends EditorUI {
    /**
     * Creates an instance of the classic editor UI class.
     *
     * @param editor The editor instance.
     * @param view The view of the UI.
     */
    constructor(editor, view) {
        super(editor);
        this.view = view;
        this._toolbarConfig = normalizeToolbarConfig(editor.config.get('toolbar'));
        this._elementReplacer = new ElementReplacer();
        this.listenTo(editor.editing.view, 'scrollToTheSelection', this._handleScrollToTheSelectionWithStickyPanel.bind(this));
    }
    /**
     * @inheritDoc
     */
    get element() {
        return this.view.element;
    }
    /**
     * Initializes the UI.
     *
     * @param replacementElement The DOM element that will be the source for the created editor.
     */
    init(replacementElement) {
        const editor = this.editor;
        const view = this.view;
        const editingView = editor.editing.view;
        const editable = view.editable;
        const editingRoot = editingView.document.getRoot();
        // The editable UI and editing root should share the same name. Then name is used
        // to recognize the particular editable, for instance in ARIA attributes.
        editable.name = editingRoot.rootName;
        view.render();
        // The editable UI element in DOM is available for sure only after the editor UI view has been rendered.
        // But it can be available earlier if a DOM element has been passed to BalloonEditor.create().
        const editableElement = editable.element;
        // Register the editable UI view in the editor. A single editor instance can aggregate multiple
        // editable areas (roots) but the classic editor has only one.
        this.setEditableElement(editable.name, editableElement);
        // Let the editable UI element respond to the changes in the global editor focus
        // tracker. It has been added to the same tracker a few lines above but, in reality, there are
        // many focusable areas in the editor, like balloons, toolbars or dropdowns and as long
        // as they have focus, the editable should act like it is focused too (although technically
        // it isn't), e.g. by setting the proper CSS class, visually announcing focus to the user.
        // Doing otherwise will result in editable focus styles disappearing, once e.g. the
        // toolbar gets focused.
        view.editable.bind('isFocused').to(this.focusTracker);
        // Bind the editable UI element to the editing view, making it an end– and entry–point
        // of the editor's engine. This is where the engine meets the UI.
        editingView.attachDomRoot(editableElement);
        // If an element containing the initial data of the editor was provided, replace it with
        // an editor instance's UI in DOM until the editor is destroyed. For instance, a <textarea>
        // can be such element.
        if (replacementElement) {
            this._elementReplacer.replace(replacementElement, this.element);
        }
        this._initPlaceholder();
        this._initToolbar();
        this._initDialogPluginIntegration();
        this.fire('ready');
    }
    /**
     * @inheritDoc
     */
    destroy() {
        super.destroy();
        const view = this.view;
        const editingView = this.editor.editing.view;
        this._elementReplacer.restore();
        editingView.detachDomRoot(view.editable.name);
        view.destroy();
    }
    /**
     * Initializes the editor toolbar.
     */
    _initToolbar() {
        const view = this.view;
        // Set–up the sticky panel with toolbar.
        view.stickyPanel.bind('isActive').to(this.focusTracker, 'isFocused');
        view.stickyPanel.limiterElement = view.element;
        view.stickyPanel.bind('viewportTopOffset').to(this, 'viewportOffset', ({ top }) => top || 0);
        view.toolbar.fillFromConfig(this._toolbarConfig, this.componentFactory);
        // Register the toolbar so it becomes available for Alt+F10 and Esc navigation.
        this.addToolbar(view.toolbar);
    }
    /**
     * Enable the placeholder text on the editing root.
     */
    _initPlaceholder() {
        const editor = this.editor;
        const editingView = editor.editing.view;
        const editingRoot = editingView.document.getRoot();
        const sourceElement = editor.sourceElement;
        let placeholderText;
        const placeholder = editor.config.get('placeholder');
        if (placeholder) {
            placeholderText = typeof placeholder === 'string' ? placeholder : placeholder[this.view.editable.name];
        }
        if (!placeholderText && sourceElement && sourceElement.tagName.toLowerCase() === 'textarea') {
            placeholderText = sourceElement.getAttribute('placeholder');
        }
        if (placeholderText) {
            editingRoot.placeholder = placeholderText;
        }
        enablePlaceholder({
            view: editingView,
            element: editingRoot,
            isDirectHost: false,
            keepOnFocus: true
        });
    }
    /**
     * Provides an integration between the sticky toolbar and {@link module:utils/dom/scroll~scrollViewportToShowTarget}.
     * It allows the UI-agnostic engine method to consider the geometry of the
     * {@link module:editor-classic/classiceditoruiview~ClassicEditorUIView#stickyPanel} that pins to the
     * edge of the viewport and can obscure the user caret after scrolling the window.
     *
     * @param evt The `scrollToTheSelection` event info.
     * @param data The payload carried by the `scrollToTheSelection` event.
     * @param originalArgs The original arguments passed to `scrollViewportToShowTarget()` method (see implementation to learn more).
     */
    _handleScrollToTheSelectionWithStickyPanel(evt, data, originalArgs) {
        const stickyPanel = this.view.stickyPanel;
        if (stickyPanel.isSticky) {
            const stickyPanelHeight = new Rect(stickyPanel.element).height;
            data.viewportOffset.top += stickyPanelHeight;
        }
        else {
            const scrollViewportOnPanelGettingSticky = () => {
                this.editor.editing.view.scrollToTheSelection(originalArgs);
            };
            this.listenTo(stickyPanel, 'change:isSticky', scrollViewportOnPanelGettingSticky);
            // This works as a post-scroll-fixer because it's impossible predict whether the panel will be sticky after scrolling or not.
            // Listen for a short period of time only and if the toolbar does not become sticky very soon, cancel the listener.
            setTimeout(() => {
                this.stopListening(stickyPanel, 'change:isSticky', scrollViewportOnPanelGettingSticky);
            }, 20);
        }
    }
    /**
     * Provides an integration between the sticky toolbar and {@link module:ui/dialog/dialog the Dialog plugin}.
     *
     * It moves the dialog down to ensure that the
     * {@link module:editor-classic/classiceditoruiview~ClassicEditorUIView#stickyPanel sticky panel}
     * used by the editor UI will not get obscured by the dialog when the dialog uses one of its automatic positions.
     */
    _initDialogPluginIntegration() {
        if (!this.editor.plugins.has('Dialog')) {
            return;
        }
        const stickyPanel = this.view.stickyPanel;
        const dialogPlugin = this.editor.plugins.get('Dialog');
        dialogPlugin.on('show', () => {
            const dialogView = dialogPlugin.view;
            dialogView.on('moveTo', (evt, data) => {
                // Engage only when the panel is sticky, and the dialog is using one of default positions.
                if (!stickyPanel.isSticky || dialogView.wasMoved) {
                    return;
                }
                const stickyPanelContentRect = new Rect(stickyPanel.contentPanelElement);
                if (data[1] < stickyPanelContentRect.bottom + DialogView.defaultOffset) {
                    data[1] = stickyPanelContentRect.bottom + DialogView.defaultOffset;
                }
            }, { priority: 'high' });
        }, { priority: 'low' });
    }
}
