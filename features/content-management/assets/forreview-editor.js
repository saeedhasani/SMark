(function (wp) {
    if (!wp || !wp.plugins || !wp.editPost || !wp.element || !wp.data || !wp.apiFetch) {
        return;
    }

    var el = wp.element.createElement;
    var useState = wp.element.useState;
    var useEffect = wp.element.useEffect;
    var useSelect = wp.data.useSelect;
    var apiFetch = wp.apiFetch;

    var registerPlugin = wp.plugins.registerPlugin;
    var PluginSidebar = wp.editPost.PluginSidebar;
    var PluginSidebarMoreMenuItem = wp.editPost.PluginSidebarMoreMenuItem;

    var PanelBody = wp.components.PanelBody;
    var Button = wp.components.Button;
    var TextControl = wp.components.TextControl;
    var Notice = wp.components.Notice;
    var Spinner = wp.components.Spinner;

    function copyText(text) {
        if (navigator.clipboard && navigator.clipboard.writeText) {
            return navigator.clipboard.writeText(text);
        }

        return new Promise(function (resolve) {
            var ta = document.createElement('textarea');
            ta.value = text;
            ta.setAttribute('readonly', 'readonly');
            ta.style.position = 'absolute';
            ta.style.left = '-9999px';
            document.body.appendChild(ta);
            ta.select();
            try {
                document.execCommand('copy');
            } catch (e) {
                // ignore
            }
            document.body.removeChild(ta);
            resolve();
        });
    }

    function Sidebar() {
        var strings = (window.SMarkForReview && window.SMarkForReview.strings) ? window.SMarkForReview.strings : {};
        var restBase = (window.SMarkForReview && window.SMarkForReview.restPath) ? String(window.SMarkForReview.restPath) : '';

        var postId = useSelect(function (select) {
            return select('core/editor').getCurrentPostId();
        });

        var status = useSelect(function (select) {
            return select('core/editor').getEditedPostAttribute('status');
        });

        var slug = useSelect(function (select) {
            return select('core/editor').getEditedPostAttribute('slug');
        });

        var _a = useState(false), enabled = _a[0], setEnabled = _a[1];
        var _b = useState(''), url = _b[0], setUrl = _b[1];
        var _c = useState(false), loading = _c[0], setLoading = _c[1];
        var _d = useState(''), error = _d[0], setError = _d[1];
        var _e = useState(false), copied = _e[0], setCopied = _e[1];

        function fetchState() {
            if (!postId || !restBase) {
                return;
            }
            setLoading(true);
            setError('');
            apiFetch({ path: restBase + postId, method: 'GET' })
                .then(function (res) {
                    setEnabled(!!(res && res.enabled));
                    setUrl((res && res.url) ? String(res.url) : '');
                })
                .catch(function () {
                    // ignore (e.g. permissions)
                })
                .finally(function () {
                    setLoading(false);
                });
        }

        useEffect(function () {
            fetchState();
            // eslint-disable-next-line react-hooks/exhaustive-deps
        }, [postId]);

        var isPublished = (status === 'publish' || status === 'private');
        var hasSlug = !!(slug && String(slug).length);

        function onEnable() {
            if (!postId || !restBase) {
                return;
            }
            setLoading(true);
            setError('');
            apiFetch({ path: restBase + postId, method: 'POST' })
                .then(function (res) {
                    setEnabled(!!(res && res.enabled));
                    setUrl((res && res.url) ? String(res.url) : '');
                })
                .catch(function (e) {
                    setError((e && e.message) ? e.message : 'Error');
                })
                .finally(function () {
                    setLoading(false);
                });
        }

        function onDisable() {
            if (!postId || !restBase) {
                return;
            }
            setLoading(true);
            setError('');
            apiFetch({ path: restBase + postId, method: 'DELETE' })
                .then(function (res) {
                    setEnabled(!!(res && res.enabled));
                    setUrl((res && res.url) ? String(res.url) : '');
                })
                .catch(function (e) {
                    setError((e && e.message) ? e.message : 'Error');
                })
                .finally(function () {
                    setLoading(false);
                });
        }

        function onCopy() {
            if (!url) {
                return;
            }
            copyText(url).then(function () {
                setCopied(true);
                setTimeout(function () {
                    setCopied(false);
                }, 1200);
            });
        }

        return el(
            wp.element.Fragment,
            {},
            el(
                PluginSidebarMoreMenuItem,
                { target: 'smark-forreview-sidebar' },
                strings.panelTitle || 'SMark'
            ),
            el(
                PluginSidebar,
                { name: 'smark-forreview-sidebar', title: strings.panelTitle || 'SMark', icon: 'admin-links' },
                el(
                    PanelBody,
                    { title: strings.featureTitle || 'مدیریت محتوا', initialOpen: true },
                    loading ? el('div', { style: { padding: '8px 0' } }, el(Spinner, {})) : null,
                    error ? el(Notice, { status: 'error', isDismissible: true, onRemove: function () { setError(''); } }, error) : null,
                    isPublished ? el(Notice, { status: 'info', isDismissible: false }, strings.notAvailable || 'این قابلیت فقط برای محتوای منتشر نشده است.') : null,
                    (!postId || !hasSlug) && !isPublished
                        ? el(Notice, { status: 'warning', isDismissible: false }, strings.saveDraftFirst || 'برای ساخت لینک موقت، ابتدا نوشته را ذخیره کنید تا اسلاگ ساخته شود.')
                        : null,
                    (!isPublished && postId && hasSlug)
                        ? (enabled
                            ? el(
                                'div',
                                {},
                                el(TextControl, { label: strings.linkLabel || 'لینک موقت:', value: url || '', readOnly: true, onChange: function () { } }),
                                el('div', { style: { display: 'flex', gap: '8px' } },
                                    el(Button, { variant: 'secondary', onClick: onCopy, disabled: !url }, copied ? (strings.copied || 'کپی شد') : (strings.copy || 'کپی')),
                                    el(Button, { variant: 'secondary', onClick: onDisable, disabled: loading }, strings.buttonRevoke || 'لغو لینک موقت')
                                )
                            )
                            : el(
                                Button,
                                { variant: 'primary', onClick: onEnable, disabled: loading },
                                strings.buttonCreate || 'ساخت لینک موقت'
                            ))
                        : null
                )
            )
        );
    }

    registerPlugin('smark-forreview', {
        render: Sidebar,
        icon: 'admin-links'
    });
})(window.wp);
