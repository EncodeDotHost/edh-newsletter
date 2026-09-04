/**
 * Newsletter block editor script
 *
 * Plain JavaScript against the wp.* globals: this plugin has no build step.
 * Block metadata (title, attributes, supports) comes from each block.json,
 * which the server registers; this file only supplies the edit component.
 * Both blocks are dynamic, so save() returns null and PHP renders the output.
 *
 * @package Newsletter
 * @since 2.1.0
 */

(function (wp) {
    'use strict';

    var el = wp.element.createElement;
    var Fragment = wp.element.Fragment;
    var __ = wp.i18n.__;
    var registerBlockType = wp.blocks.registerBlockType;
    var InspectorControls = wp.blockEditor.InspectorControls;
    var useBlockProps = wp.blockEditor.useBlockProps;
    var PanelBody = wp.components.PanelBody;
    var TextControl = wp.components.TextControl;
    var TextareaControl = wp.components.TextareaControl;
    var SelectControl = wp.components.SelectControl;
    var ToggleControl = wp.components.ToggleControl;
    var Disabled = wp.components.Disabled;
    var ServerSideRender = wp.serverSideRender;

    /**
     * Server-rendered preview, wrapped in Disabled so the form cannot be submitted inside the editor.
     */
    function preview(blockName, attributes) {
        return el(
            'div',
            useBlockProps(),
            el(Disabled, null, el(ServerSideRender, { block: blockName, attributes: attributes }))
        );
    }

    function setter(props, key) {
        return function (value) {
            var change = {};
            change[key] = value;
            props.setAttributes(change);
        };
    }

    registerBlockType('edh-newsletter/signup-form', {
        edit: function (props) {
            var a = props.attributes;

            return el(
                Fragment,
                null,
                el(
                    InspectorControls,
                    null,
                    el(
                        PanelBody,
                        { title: __('Form Content', 'edh-newsletter'), initialOpen: true },
                        el(TextControl, {
                            label: __('Title', 'edh-newsletter'),
                            value: a.title,
                            onChange: setter(props, 'title')
                        }),
                        el(TextareaControl, {
                            label: __('Description', 'edh-newsletter'),
                            value: a.description,
                            onChange: setter(props, 'description')
                        }),
                        el(TextControl, {
                            label: __('Button text', 'edh-newsletter'),
                            value: a.buttonText,
                            onChange: setter(props, 'buttonText')
                        })
                    ),
                    el(
                        PanelBody,
                        { title: __('Options', 'edh-newsletter'), initialOpen: true },
                        el(ToggleControl, {
                            label: __('Let visitors choose weekly or monthly', 'edh-newsletter'),
                            checked: !!a.showFrequency,
                            onChange: setter(props, 'showFrequency')
                        }),
                        el(SelectControl, {
                            label: __('Default frequency', 'edh-newsletter'),
                            value: a.defaultFrequency,
                            options: [
                                { label: __('Weekly', 'edh-newsletter'), value: 'weekly' },
                                { label: __('Monthly', 'edh-newsletter'), value: 'monthly' }
                            ],
                            onChange: setter(props, 'defaultFrequency')
                        }),
                        el(SelectControl, {
                            label: __('Style', 'edh-newsletter'),
                            value: a.style,
                            options: [
                                { label: __('Default', 'edh-newsletter'), value: 'default' },
                                { label: __('Minimal', 'edh-newsletter'), value: 'minimal' },
                                { label: __('Boxed', 'edh-newsletter'), value: 'boxed' },
                                { label: __('Inline', 'edh-newsletter'), value: 'inline' }
                            ],
                            onChange: setter(props, 'style')
                        })
                    )
                ),
                preview('edh-newsletter/signup-form', a)
            );
        },
        save: function () {
            return null;
        }
    });

    registerBlockType('edh-newsletter/preferences-form', {
        edit: function (props) {
            var a = props.attributes;

            return el(
                Fragment,
                null,
                el(
                    InspectorControls,
                    null,
                    el(
                        PanelBody,
                        { title: __('Form Content', 'edh-newsletter'), initialOpen: true },
                        el(TextControl, {
                            label: __('Title', 'edh-newsletter'),
                            value: a.title,
                            onChange: setter(props, 'title')
                        })
                    )
                ),
                preview('edh-newsletter/preferences-form', a)
            );
        },
        save: function () {
            return null;
        }
    });
})(window.wp);
