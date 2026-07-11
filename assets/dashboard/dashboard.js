(function(wp, window, document) {
    'use strict';

    if (!wp || !wp.element) {
        return;
    }

    const { createElement: h, useEffect, useState } = wp.element;
    const config = window.SMarkDashboard || {};
    const strings = config.strings || {};
    const stringsByLang = config.stringsByLang || {};
    const logoUrl = config.logoUrl || '';
    const urls = config.urls || {};
    const dailyGuideCards = Array.isArray(config.dailyGuideCards) ? config.dailyGuideCards : [];
    const emailWorkflow = config.emailWorkflow || {};
    const initialOfferProducts = Array.isArray(config.offerProducts) ? config.offerProducts : [];
    const initialOfferSections = config.offerSections && typeof config.offerSections === 'object' ? config.offerSections : { product: initialOfferProducts };

    const text = function(key, fallback) {
        return strings[key] || fallback;
    };

    const textForLanguage = function(language, key, fallback) {
        const langStrings = stringsByLang[language] || {};
        return langStrings[key] || text(key, fallback);
    };

    const icons = {
        social: 'M360 0c-51.9 0-95.3 36.6-105.6 85.4L142.2 149.5c-10.8-3.6-22.3-5.5-34.2-5.5-59.6 0-108 48.4-108 108l0 36c0 59.6 48.4 108 108 108 11.9 0 23.4-1.9 34.2-5.5l112.2 64.1c10.4 48.8 53.7 85.4 105.6 85.4 59.6 0 108-48.4 108-108l0-36c0-59.6-48.4-108-108-108-11.9 0-23.4 1.9-34.2 5.5l-41.2-23.5 41.2-23.5c10.8 3.6 22.3 5.5 34.2 5.5 59.6 0 108-48.4 108-108l0-36C468 48.4 419.6 0 360 0zm72 108c0 39.8-32.2 72-72 72-10.2 0-19.9-2.1-28.6-5.9-5.2-2.3-11.2-1.9-16.1 .9L207.9 236.4c-11.9 6.8-11.9 24.5 0 31.3L315.3 329c4.9 2.8 10.9 3.1 16.1 .9 8.7-3.8 18.4-5.9 28.6-5.9 39.8 0 72 32.2 72 72s-32.2 72-72 72c-36.7 0-67.1-27.5-71.5-63.1-.7-5.6-4-10.6-8.9-13.4L152.7 319c-4.9-2.8-10.9-3.1-16.1-.9-8.7 3.8-18.4 5.9-28.6 5.9-39.8 0-72-32.2-72-72s32.2-72 72-72c10.2 0 19.9 2.1 28.6 5.9 5.2 2.3 11.2 1.9 16.1-.9l126.9-72.5c4.9-2.8 8.2-7.8 8.9-13.4 4.4-35.5 34.7-63.1 71.5-63.1 39.8 0 72 32.2 72 72z',
        seo: 'M306 225a99 99 0 1 1 -198 0 99 99 0 1 1 198 0zm-38.6 18c-7.8-26-31.9-45-60.4-45s-52.6 18.9-60.4 45c7.7 26 31.9 45 60.4 45s52.7-19 60.4-45zm137.7 78.3c5.8-19.1 8.9-39.3 8.9-60.3l0-36c0-114.3-92.7-207-207-207S0 110.7 0 225l0 36c0 114.3 92.7 207 207 207 30.8 0 60.1-6.7 86.4-18.8l69.7 69.7c28.1 28.1 73.7 28.1 101.8 0 14.1-14.1 21.1-32.5 21.1-50.9l0-36c0-18.4-7-36.9-21.1-50.9l-59.8-59.8zM207 396a171 171 0 1 1 0-342 171 171 0 1 1 0 342zm232.5 10.5c14.1 14.1 14.1 36.9 0 50.9s-36.9 14.1-50.9 0l-62.8-62.8c19.8-13.9 37-31.1 50.9-50.9l62.8 62.8z',
        email: 'M252 216a36 36 0 1 0 0 72 36 36 0 1 0 0-72zM0 252C0 112.8 112.8 0 252 0S504 112.8 504 252l0 36c0 69.6-56.4 126-126 126-15.7 0-30.9-1.2-45.9-6.3-2.6 1.8-5.3 3.4-8.1 5l0 55.3c0 39.8-32.2 72-72 72-139.2 0-252-112.8-252-252l0-36zm36 0c0 119.3 96.7 216 216 216 19.9 0 36-16.1 36-36s-16.1-36-36-36c-79.5 0-144-64.5-144-144s64.5-144 144-144 144 64.5 144 144c0 9.9-8.1 18-18 18s-18-8.1-18-18c0-59.6-48.4-108-108-108s-108 48.4-108 108 48.4 108 108 108c25.1 0 48.1-8.5 66.4-22.8 5.2-4.1 12.3-5 18.4-2.3 13.2 5.9 26.9 7.1 41.2 7.1 49.7 0 90-40.3 90-90 0-119.3-96.7-216-216-216S36 132.7 36 252z',
        ads: 'M42.5 186.3C15.6 198.4-.9 225.5 0 254.4L.7 297c.5 32.8 33.7 59.6 64.1 62.7l121.4 12.1 12.1 121.4c3.2 32.4 27.9 58.7 60.1 63.9s63.9-11.8 77.2-41.5l162-360c4.2-9.4 6.3-19.4 6.3-29.3l0-35.8c.1-18.9-7.2-37.4-21.1-51.3-21.2-21.2-53.2-27-80.5-14.7l-360 162zm415-121.8c10.6 10.6 13.5 26.6 7.4 40.2l-162 360c-6.7 14.9-22.5 23.4-38.6 20.8s-28.4-15.7-30-31.9l-13.6-136c-.9-8.5-7.6-15.3-16.1-16.1l-136-13.6c-16.2-1.6-29.3-13.9-31.9-30-2.6-15.8 6.1-32 20.8-38.6l360-162c13.6-6.1 29.7-3.2 40.2 7.4z',
        offer: 'M432 180c0-39.8-32.2-72-72-72l0-36c0-18.4-7-36.9-21.1-50.9-28.1-28.1-73.7-28.1-101.8 0L216 42.2 194.9 21.1C166.8-7 121.2-7 93.1 21.1 80.5 33.7 72 51.8 72 69.8L72 108c-39.8 0-72 32.2-72 72L0 432c0 39.8 32.2 72 72 72l288 0c39.8 0 72-32.2 72-72l0-252zM118.5 46.5c14.1-14.1 36.9-14.1 50.9 0l33.8 33.8c6.9 6.9 18.5 6.9 25.5 0l33.8-33.8c14.1-14.1 36.9-14.1 50.9 0s14.1 36.9 0 50.9l-10.5 10.5-173.8 0-10.5-10.5c-14.1-14.1-14.1-36.9 0-50.9zM72 144l72 0 0 126-108 0 0-90c0-19.9 16.1-36 36-36zm72 162l0 126-72 0c-19.9 0-36-16.1-36-36l0-90 108 0zM252 144l0 288-72 0 0-288 72 0zm36 126l0-126 72 0c19.9 0 36 16.1 36 36l0 90-108 0zm0 36l108 0 0 90c0 19.9-16.1 36-36 36l-72 0 0-126z',
        creditCard: 'M288 288c-9.9 0-18 8.1-18 18s8.1 18 18 18l90 0c9.9 0 18-8.1 18-18s-8.1-18-18-18l-90 0zM0 126C0 86.2 32.2 54 72 54l360 0c39.8 0 72 32.2 72 72l0 288c0 39.8-32.2 72-72 72L72 486c-39.8 0-72-32.2-72-72L0 126zm36 72l0 180c0 19.9 16.1 36 36 36l360 0c19.9 0 36-16.1 36-36l0-180-432 0zm432-36l0-36c0-19.9-16.1-36-36-36L72 90c-19.9 0-36 16.1-36 36l0 36 432 0z',
        done: 'M0 252c0-18.5 7-36.8 21.1-50.9 28.1-28.1 73.7-28.1 101.8 0L180 258.2 381.1 57.1C409.2 29 454.8 29 482.9 57.1 497 71.1 504 89.6 504 108l0 36c0 18.4-7 36.9-21.1 50.9l-252 252c-28.1 28.1-73.7 28.1-101.8 0l-108-108C7.1 325 .1 306.6 0 288.3L0 252zM457.5 82.5c-14.1-14.1-36.9-14.1-50.9 0L192.7 296.4c-7 7-18.4 7-25.5 0L97.5 226.5c-14.1-14.1-36.9-14.1-50.9 0-7 7-10.5 16.3-10.5 25.5 0 9.2 3.5 18.4 10.5 25.4l108 108c14.1 14.1 36.9 14.1 50.9 0l252-252c14.1-14.1 14.1-36.9 0-50.9z',
        language: 'M0 126C0 86.2 32.2 54 72 54l360 0c39.8 0 72 32.2 72 72l0 288c0 39.8-32.2 72-72 72L72 486c-39.8 0-72-32.2-72-72L0 126zM36 378c0 19.9 16.1 36 36 36l360 0c19.9 0 36-16.1 36-36l0-252c0-19.9-16.1-36-36-36L72 90c-19.9 0-36 16.1-36 36l0 252zM342 171c7.1 0 13.6 4.2 16.4 10.7l72 162c4 9.1-.1 19.7-9.1 23.8s-19.7-.1-23.8-9.1l-19.2-43.3-72.6 0-19.2 43.3c-4 9.1-14.7 13.2-23.8 9.1s-13.2-14.7-9.1-23.8l12.8-28.7-14.3 0c-28.6 0-52.4-5.9-72-15.3-19.7 9.4-43.4 15.3-72 15.3-9.9 0-18-8.1-18-18s8.1-18 18-18c13.1 0 24.9-1.5 35.4-4.2-13.8-12.8-24.1-27.2-31.8-41.1-4.8-8.7-1.7-19.6 7-24.5s19.6-1.7 24.5 7c8.2 14.8 19.9 30.1 36.9 42 27.6-19.2 41.9-48.6 48.8-69.2L108 189c-9.9 0-18-8.1-18-18s8.1-18 18-18l54 0 0-18c0-9.9 8.1-18 18-18s18 8.1 18 18l0 18 54 0c5.2 0 10.2 2.3 13.6 6.2s5 9.2 4.2 14.3c-1.1 7.6-3.1 15.1-5.4 22.5-4 12.9-10.8 30.2-22 47.7-6.9 10.7-15.4 21.3-25.8 31.1 10.5 2.7 22.3 4.2 35.4 4.2l30.3 0 43.2-97.3c2.9-6.5 9.3-10.7 16.4-10.7zm0 62.3l-20.3 45.7 40.6 0-20.3-45.7z',
        settings: 'M155.4 61.1C160.8 25.9 191 0 226.6 0l15.3 0c35.5 0 65.8 25.9 71.2 61.1l4.9 31.5c4.3 2.2 8.4 4.7 12.5 7.2l29.8-11.6c33.1-12.9 70.7 .3 88.5 31.1l7.7 13.3c6.6 11.5 9.8 24.1 9.6 36.6l0 35.8c-.2 21-9.5 41.8-26.9 55.8l-11.5 9.2 11.5 9.2c17.7 14.2 27.1 35.3 26.9 56.7l0 35c.1 12.4-3.1 25-9.6 36.4l-7.7 13.3c-17.8 30.8-55.3 44-88.5 31.1l-29.8-11.6c-4.1 2.6-8.2 5-12.5 7.2l-4.9 31.5c-5.4 35.1-35.6 61.1-71.2 61.1l-15.3 0c-35.5 0-65.8-25.9-71.2-61.1l-4.9-31.5c-4.3-2.2-8.4-4.7-12.5-7.2l-29.8 11.6c-33.1 12.9-70.7-.3-88.5-31.1l-7.7-13.3C5.6 396 2.4 383.4 2.5 371l0-35c-.2-21.4 9.2-42.5 26.9-56.7l11.5-9.2-11.5-9.2C12 246.8 2.7 226 2.5 205l0-35.8c-.1-12.5 3-25.1 9.6-36.6l7.7-13.3c17.8-30.8 55.3-44 88.5-31.1L138 99.8c4.1-2.6 8.2-5 12.5-7.2l4.9-31.5zM226.6 36c-17.8 0-32.9 13-35.6 30.5l-6.3 40.8c-.9 6-4.8 11.1-10.3 13.6-8.3 3.8-16.3 8.4-23.7 13.7-4.9 3.5-11.3 4.3-17 2.1l-38.5-15c-16.6-6.4-35.3 .2-44.2 15.6l-7.7 13.3c-8.9 15.4-5.2 35 8.6 46.1l32.2 25.9c4.7 3.8 7.2 9.7 6.7 15.7-.8 9-.8 18.4 0 27.4 .6 6-1.9 11.9-6.7 15.7L52 307.3c-13.9 11.1-17.5 30.7-8.6 46.1L51 366.6c8.9 15.4 27.7 22 44.2 15.6l38.5-15c5.7-2.2 12-1.4 17 2.1 7.4 5.3 15.3 9.9 23.7 13.7 5.5 2.5 9.4 7.6 10.3 13.6l6.3 40.8c2.7 17.6 17.8 30.5 35.6 30.5l15.3 0c17.8 0 32.9-13 35.6-30.5l6.3-40.8c.9-6 4.8-11.1 10.3-13.6 8.3-3.8 16.3-8.4 23.7-13.7 4.9-3.5 11.3-4.3 17-2.1l38.5 15c16.6 6.4 35.3-.2 44.2-15.6l7.7-13.3c8.9-15.4 5.2-35-8.6-46.1l-32.2-25.9c-4.7-3.8-7.2-9.7-6.7-15.7 .8-9 .8-18.4 0-27.4-.6-6 1.9-11.9 6.7-15.7l32.2-25.9c13.9-11.1 17.5-30.7 8.6-46.1l-7.7-13.3c-8.9-15.4-27.7-22-44.2-15.6l-38.5 15c-5.7 2.2-12 1.4-17-2.1-7.4-5.3-15.3-9.9-23.7-13.7-5.5-2.5-9.4-7.6-10.3-13.6l-6.3-40.8C274.8 49 259.7 36 241.9 36l-15.3 0zm79.7 216a72 72 0 1 1 -144 0 72 72 0 1 1 144 0zM203 270c6.2 10.8 17.9 18 31.2 18s25-7.2 31.2-18c-6.3-10.8-17.9-18-31.2-18s-24.9 7.2-31.2 18z',
    };

    function SvgIcon(props) {
        return h('svg', {
            className: 'smark-dashboard-glass-menu__svg-icon',
            xmlns: 'http://www.w3.org/2000/svg',
            viewBox: props.viewBox || '0 0 504 540',
            'aria-hidden': true,
            focusable: false,
        }, h('path', { fill: 'currentColor', d: props.path }));
    }

    function MenuItem(props) {
        const classes = 'smark-dashboard-glass-menu__item' + (props.active ? ' is-active' : '');
        const tooltip = props.tooltipMeta
            ? h('span', { className: 'smark-dashboard-glass-menu__tooltip' },
                h('span', { className: 'smark-dashboard-glass-menu__tooltip-title' }, props.label),
                h('span', { className: 'smark-dashboard-glass-menu__tooltip-spacer' }, ' '),
                h('span', { className: 'smark-dashboard-glass-menu__tooltip-meta' }, props.tooltipMeta)
            )
            : h('span', { className: 'smark-dashboard-glass-menu__tooltip' }, props.label);

        if (props.href) {
            return h('a', {
                className: classes,
                href: props.href,
                'aria-label': props.label,
            }, props.children, tooltip);
        }

        return h('button', {
            type: 'button',
            className: classes,
            'aria-label': props.label,
            'aria-expanded': props.expanded,
            onClick: props.onClick,
        }, props.children, tooltip);
    }

    function CategoryIcon(props) {
        if (props.category === 'smark') {
            return h('span', {
                className: 'smark-dashboard-daily-card__logo',
                'aria-hidden': true,
                style: {
                    WebkitMaskImage: logoUrl ? 'url("' + logoUrl + '")' : undefined,
                    maskImage: logoUrl ? 'url("' + logoUrl + '")' : undefined,
                },
            });
        }

        const category = props.category === 'social' || props.category === 'email' ? props.category : 'seo';
        return h(SvgIcon, {
            path: icons[category],
            viewBox: '0 0 504 540',
        });
    }

    function DailyGuideCard(props) {
        const card = props.card || {};
        const language = props.language === 'fa' ? 'fa' : 'en';
        const title = language === 'fa' ? (card.titleFa || card.title) : (card.titleEn || card.title);
        const description = language === 'fa' ? (card.descriptionFa || card.description) : (card.descriptionEn || card.description);
        const completed = !!card.completed;
        const smartActionEnabled = card.smartActionEnabled !== false || card.key === 'email_contacts_daily';
        const agentMarkCost = parseInt(card.agentMarkCost || 0, 10);

        return h('article', { className: 'smark-dashboard-daily-card' + (completed ? ' is-complete' : '') },
            h('div', { className: 'smark-dashboard-daily-card__top' },
                h('span', { className: 'smark-dashboard-daily-card__icon' },
                    h(CategoryIcon, { category: card.category || 'seo' })
                ),
                h('h3', { className: 'smark-dashboard-daily-card__title' }, title || '')
            ),
            h('div', { className: 'smark-dashboard-daily-card__rule', 'aria-hidden': true }),
            h('p', { className: 'smark-dashboard-daily-card__description' }, description || ''),
            h('div', { className: 'smark-dashboard-daily-card__actions' },
                h('a', {
                    className: 'smark-dashboard-daily-card__button smark-dashboard-daily-card__button--primary daily-guide-btn',
                    href: card.url || '#',
                    'aria-disabled': completed ? true : undefined,
                    tabIndex: completed ? -1 : undefined,
                    onClick: completed ? function(event) {
                        event.preventDefault();
                    } : card.emailView && typeof props.onOpenEmailView === 'function' ? function(event) {
                        event.preventDefault();
                        props.onOpenEmailView(card.emailView);
                    } : undefined,
                }, textForLanguage(language, 'open', 'Open')),
                h('button', {
                    type: 'button',
                    className: 'smark-dashboard-daily-card__button daily-guide-btn daily-guide-btn--smart',
                    'data-smark-daily-guide-smart': completed || !smartActionEnabled ? undefined : '1',
                    'data-smark-daily-guide-key': completed || !smartActionEnabled ? undefined : (card.key || ''),
                    disabled: completed || !smartActionEnabled,
                    onClick: completed || !smartActionEnabled ? undefined : function(event) {
                        if (card.key === 'email_contacts_daily' && typeof props.onSmartAction === 'function') {
                            event.preventDefault();
                            event.stopPropagation();
                            props.onSmartAction(card);
                        }
                    },
                }, [
                    h('span', { key: 'label', className: 'smark-dashboard-daily-card__button-label' }, textForLanguage(language, 'smartAction', 'Agent do')),
                    agentMarkCost > 0 ? h('span', {
                        key: 'cost',
                        className: 'smark-dashboard-daily-card__mark-cost',
                        'aria-label': String(agentMarkCost) + ' Mark',
                    }, String(agentMarkCost)) : null,
                ])
            ),
            completed ? h('div', { className: 'smark-dashboard-daily-card__complete-overlay', 'aria-hidden': true },
                h(SvgIcon, { path: icons.done, viewBox: '0 0 504 540' })
            ) : null
        );
    }

    function DailyGuideGrid(props) {
        const language = props.language === 'fa' ? 'fa' : 'en';
        const moduleVisibility = props.moduleVisibility || {};
        const visibleCards = dailyGuideCards.filter(function(card) {
            const category = card && card.category ? card.category : '';
            return !category || moduleVisibility[category] !== false;
        });

        if (!visibleCards.length) {
            return h('section', { className: 'smark-dashboard-daily-grid smark-dashboard-daily-grid--empty' },
                h('div', { className: 'smark-dashboard-daily-card smark-dashboard-daily-card--empty' },
                    h('p', { className: 'smark-dashboard-daily-card__description' }, textForLanguage(language, 'dailyGuideAllGood', 'All set for today. Great job!'))
                )
            );
        }

        return h('section', { className: 'smark-dashboard-daily-grid', 'aria-label': textForLanguage(language, 'dailyGuideTitle', 'Daily Guide') },
            visibleCards.map(function(card) {
                return h(DailyGuideCard, {
                    key: card.key || card.title,
                    card: card,
                    language: language,
                    onOpenEmailView: props.onOpenEmailView,
                    onSmartAction: props.onSmartAction,
                });
            })
        );
    }

    function EmailWorkflowPanel(props) {
        const language = props.language === 'fa' ? 'fa' : 'en';
        const workflow = emailWorkflow[language] || emailWorkflow.en || {};
        const tasks = Array.isArray(workflow.tasks) ? workflow.tasks : [];

        return h('section', { className: 'smark-dashboard-email-workflow smark-dashboard-view', 'aria-label': workflow.sectionTitle || text('emailMarketing', 'Email Marketing') },
            h('header', { className: 'smark-dashboard-email-workflow__header' },
                h('h2', null, workflow.sectionTitle || text('emailMarketing', 'Email Marketing')),
                h('p', null, workflow.sectionDescription || '')
            ),
            h('ul', { className: 'smark-dashboard-email-workflow__list' },
                tasks.map(function(task, index) {
                    const content = h('div', { className: 'smark-dashboard-email-workflow__content' },
                        h('span', { className: 'smark-dashboard-email-workflow__icon dashicons ' + (task.icon || 'dashicons-email-alt'), 'aria-hidden': true }),
                        h('span', { className: 'smark-dashboard-email-workflow__text' },
                            h('strong', null, task.title || ''),
                            h('small', null, task.description || '')
                        )
                    );

                    return h('li', { key: task.title || index, className: 'smark-dashboard-email-workflow__item' },
                        task.view
                            ? h('button', {
                                type: 'button',
                                className: 'smark-dashboard-email-workflow__link smark-dashboard-email-workflow__button',
                                onClick: function() {
                                    if (typeof props.onOpenView === 'function') {
                                        props.onOpenView(task.view);
                                    }
                                },
                            }, content)
                            : task.url
                            ? h('a', { className: 'smark-dashboard-email-workflow__link', href: task.url }, content)
                            : content
                    );
                })
            )
        );
    }

    function OfferPanel(props) {
        const sections = Array.isArray(props.sections) ? props.sections : [];
        const activeSection = props.activeSection || 'product';
        const currentSection = sections.filter(function(section) {
            return section.id === activeSection;
        })[0] || sections[0] || { id: 'product', title: text('offerProductsTitle', 'Products') };
        const products = props.itemsBySection && Array.isArray(props.itemsBySection[currentSection.id]) ? props.itemsBySection[currentSection.id] : [];
        const form = props.form || {};
        const isProduct = currentSection.id === 'product';

        return h('section', { className: 'smark-dashboard-offer smark-dashboard-view', 'aria-label': text('offerManagementTitle', 'Offering Management') },
            h('header', { className: 'smark-dashboard-offer__header' },
                h('div', null,
                    h('h2', null, text('offerManagementTitle', 'Offering Management')),
                    h('p', null, text('offerManagementDescription', 'Define campaign products, audiences, strategies, and offers in one organized workspace.'))
                ),
                props.notice ? h('span', { className: 'smark-dashboard-offer__notice' + (props.noticeType === 'error' ? ' is-error' : '') }, props.notice) : null
            ),
            h('div', { className: 'smark-dashboard-offer__tabs', role: 'tablist', 'aria-label': text('offerManagementTitle', 'Offering Management') },
                sections.map(function(section) {
                    const selected = section.id === currentSection.id;
                    return h('button', {
                        key: section.id,
                        type: 'button',
                        role: 'tab',
                        'aria-selected': selected,
                        className: 'smark-dashboard-offer__tab' + (selected ? ' is-active' : ''),
                        onClick: function() {
                            props.onSectionChange(section.id);
                        },
                    }, section.title);
                })
            ),
            h('div', { className: 'smark-dashboard-offer__section-heading' },
                h('h3', null, currentSection.title || ''),
                h('p', null, currentSection.description || '')
            ),
            h('form', {
                className: 'smark-dashboard-offer__form',
                onSubmit: function(event) {
                    event.preventDefault();
                    if (typeof props.onSubmit === 'function') {
                        props.onSubmit();
                    }
                },
            },
                h('label', null,
                    h('span', null, isProduct ? text('offerProductName', 'Product name') : text('offerItemName', 'Title')),
                    h('input', {
                        type: 'text',
                        value: form.name || '',
                        onChange: function(event) { props.onFormChange('name', event.target.value); },
                    })
                ),
                h('label', null,
                    h('span', null, text('offerProductPrice', 'Price')),
                    h('input', {
                        type: 'text',
                        value: form.price || '',
                        onChange: function(event) { props.onFormChange('price', event.target.value); },
                    })
                ),
                h('label', null,
                    h('span', null, text('offerProductUrl', 'Product URL')),
                    h('input', {
                        type: 'url',
                        value: form.url || '',
                        onChange: function(event) { props.onFormChange('url', event.target.value); },
                    })
                ),
                h('label', { className: 'smark-dashboard-offer__field-wide' },
                    h('span', null, text('offerProductNotes', 'Notes')),
                    h('textarea', {
                        rows: 3,
                        value: form.notes || '',
                        onChange: function(event) { props.onFormChange('notes', event.target.value); },
                    })
                ),
                h('div', { className: 'smark-dashboard-offer__actions' },
                    h('button', { type: 'submit', className: 'smark-dashboard-offer__button smark-dashboard-offer__button--primary', disabled: props.saving },
                        props.editingId
                            ? (isProduct ? text('offerProductUpdate', 'Update product') : text('offerItemUpdate', 'Update item'))
                            : (isProduct ? text('offerProductAdd', 'Add product') : text('offerItemAdd', 'Add item'))
                    ),
                    props.editingId ? h('button', {
                        type: 'button',
                        className: 'smark-dashboard-offer__button',
                        onClick: props.onCancelEdit,
                    }, text('offerProductCancel', 'Cancel')) : null
                )
            ),
            h('div', { className: 'smark-dashboard-offer__list' },
                products.length
                    ? h('table', { className: 'smark-dashboard-offer__table' },
                        h('thead', null,
                            h('tr', null,
                                h('th', null, isProduct ? text('offerProductName', 'Product name') : text('offerItemName', 'Title')),
                                h('th', null, text('offerProductPrice', 'Price')),
                                h('th', null, text('offerProductUrl', 'Product URL')),
                                h('th', null, text('offerProductNotes', 'Notes')),
                                h('th', null, '')
                            )
                        ),
                        h('tbody', null,
                            products.map(function(product) {
                                return h('tr', { key: product.id || product.name },
                                    h('td', null, product.name || ''),
                                    h('td', null, product.price || ''),
                                    h('td', null, product.url ? h('a', { href: product.url, target: '_blank', rel: 'noreferrer' }, product.url) : ''),
                                    h('td', null, product.notes || ''),
                                    h('td', null,
                                        h('div', { className: 'smark-dashboard-offer__row-actions' },
                                            h('button', {
                                                type: 'button',
                                                className: 'smark-dashboard-offer__link-button',
                                                onClick: function() { props.onEdit(product); },
                                            }, text('offerProductEdit', 'Edit')),
                                            h('button', {
                                                type: 'button',
                                                className: 'smark-dashboard-offer__link-button is-danger',
                                                onClick: function() { props.onDelete(product.id); },
                                            }, text('offerProductDelete', 'Delete'))
                                        )
                                    )
                                );
                            })
                        )
                    )
                    : h('div', { className: 'smark-dashboard-offer__empty' }, isProduct ? text('offerProductEmpty', 'No products have been added yet.') : text('offerSectionEmpty', 'No items have been added to this section yet.'))
            )
        );
    }

    function getSignalHireFields() {
        return [
            { key: 'profile_name', label: text('signalhireProfileName', 'Profile name'), section: 'profile' },
            { key: 'profile_location', label: text('signalhireProfileLocation', 'Profile location'), section: 'profile' },
            { key: 'job_title', label: text('signalhireJobTitle', 'Job title'), section: 'profile' },
            { key: 'department', label: text('signalhireDepartment', 'Department'), section: 'profile' },
            { key: 'seniority_level', label: text('signalhireSeniorityLevel', 'Seniority level'), section: 'profile' },
            { key: 'years_experience', label: text('signalhireYearsExperience', 'Years of experience'), section: 'profile' },
            { key: 'education', label: text('signalhireEducation', 'Education'), section: 'profile' },
            { key: 'keywords', label: text('signalhireKeywords', 'Keywords'), section: 'profile' },
            { key: 'company_name', label: text('signalhireCompanyName', 'Company name'), section: 'company' },
            { key: 'company_location', label: text('signalhireCompanyLocation', 'Company location'), section: 'company' },
            { key: 'industry', label: text('signalhireIndustry', 'Industry'), section: 'company' },
            { key: 'company_size', label: text('signalhireCompanySize', 'Company size'), section: 'company' },
        ];
    }

    function SignalHireSettingsPanel(props) {
        const fields = getSignalHireFields();
        const form = props.form || {};
        const sections = [
            { id: 'profile', title: text('signalhireProfileSection', 'Profile') },
            { id: 'company', title: text('signalhireCompanySection', 'Company') },
        ];

        return h('section', { className: 'smark-dashboard-signalhire-panel smark-dashboard-view', 'aria-label': text('signalhireTitle', 'Contact Search Settings') },
            h('header', { className: 'smark-dashboard-signalhire-panel__header' },
                h('div', null,
                    h('h2', null, text('signalhireTitle', 'Contact Search Settings')),
                    h('p', null, text('signalhireIntro', 'Fill at least one field to save future contact search settings.'))
                ),
                h('button', {
                    type: 'button',
                    className: 'smark-dashboard-signalhire-panel__close',
                    'aria-label': text('close', 'Close'),
                    onClick: props.onClose,
                }, 'x')
            ),
            h('form', {
                className: 'smark-dashboard-signalhire-panel__body',
                onSubmit: function(event) {
                    event.preventDefault();
                    if (typeof props.onSubmit === 'function') {
                        props.onSubmit();
                    }
                },
            },
                h('div', { className: 'smark-dashboard-signalhire-panel__notice' }, text('signalhireInactive', 'Search execution is inactive for now; only settings are saved.')),
                sections.map(function(section) {
                    return h('section', { key: section.id, className: 'smark-dashboard-signalhire-panel__section' },
                        h('h3', null, section.title),
                        h('div', { className: 'smark-dashboard-signalhire-panel__grid' },
                            fields.filter(function(field) {
                                return field.section === section.id;
                            }).map(function(field) {
                                return h('label', { key: field.key },
                                    h('span', null, field.label),
                                    h('input', {
                                        type: 'text',
                                        value: form[field.key] || '',
                                        onChange: function(event) {
                                            props.onChange(field.key, event.target.value);
                                        },
                                    })
                                );
                            })
                        )
                    );
                }),
                props.message ? h('div', { className: 'smark-dashboard-signalhire-panel__message ' + (props.messageType === 'error' ? 'is-error' : 'is-success') }, props.message) : null,
                h('div', { className: 'smark-dashboard-signalhire-panel__actions' },
                    h('button', {
                        type: 'submit',
                        className: 'smark-dashboard-offer__button smark-dashboard-offer__button--primary',
                        disabled: props.saving,
                    }, props.saving ? text('smartRunning', 'Agent is working...') : text('signalhireSave', 'Run agent'))
                )
            )
        );
    }

    function DashboardApp() {
        const [active, setActive] = useState('smark');
        const [emailSubView, setEmailSubView] = useState('workflow');
        const [emailContactsHtml, setEmailContactsHtml] = useState('');
        const [emailContactsLoading, setEmailContactsLoading] = useState(false);
        const [emailContactsError, setEmailContactsError] = useState('');
        const [emailAccountsHtml, setEmailAccountsHtml] = useState('');
        const [emailAccountsLoading, setEmailAccountsLoading] = useState(false);
        const [emailAccountsError, setEmailAccountsError] = useState('');
        const [emailCampaignMessageHtml, setEmailCampaignMessageHtml] = useState('');
        const [emailCampaignMessageLoading, setEmailCampaignMessageLoading] = useState(false);
        const [emailCampaignMessageError, setEmailCampaignMessageError] = useState('');
        const [emailPerformanceHtml, setEmailPerformanceHtml] = useState('');
        const [emailPerformanceLoading, setEmailPerformanceLoading] = useState(false);
        const [emailPerformanceError, setEmailPerformanceError] = useState('');
        const [projectSettingsHtml, setProjectSettingsHtml] = useState('');
        const [projectSettingsLoading, setProjectSettingsLoading] = useState(false);
        const [projectSettingsError, setProjectSettingsError] = useState('');
        const [languageOpen, setLanguageOpen] = useState(false);
        const [markOpen, setMarkOpen] = useState(false);
        const [settingsOpen, setSettingsOpen] = useState(false);
        const [language, setLanguage] = useState(config.lang === 'en' ? 'en' : 'fa');
        const [moduleVisibility, setModuleVisibility] = useState(config.moduleVisibility || {});
        const [markBalance, setMarkBalance] = useState(config.markBalance || {});
        const emptyOfferProductForm = { name: '', price: '', url: '', notes: '' };
        const offerSections = [
            { id: 'product', title: text('offerProductsTitle', 'Products'), description: text('offerProductsDescription', 'Define the products available for your campaigns.') },
            { id: 'audience_type', title: text('offerAudienceTypeTitle', 'Audience Types'), description: text('offerAudienceTypeDescription', 'Define audience groups by needs and motivation.') },
            { id: 'strategy', title: text('offerStrategyTitle', 'Strategies'), description: text('offerStrategyDescription', 'Define positioning, core message, and sales logic.') },
            { id: 'offer', title: text('offerOfferTitle', 'Offers'), description: text('offerOfferDescription', 'Define incentives, benefits, and purchase triggers.') },
        ];
        const normalizedInitialOfferSections = offerSections.reduce(function(acc, section) {
            acc[section.id] = Array.isArray(initialOfferSections[section.id]) ? initialOfferSections[section.id] : [];
            return acc;
        }, {});
        if (!normalizedInitialOfferSections.product.length && initialOfferProducts.length) {
            normalizedInitialOfferSections.product = initialOfferProducts;
        }
        const [offerActiveSection, setOfferActiveSection] = useState('product');
        const [offerItemsBySection, setOfferItemsBySection] = useState(normalizedInitialOfferSections);
        const [offerProductForm, setOfferProductForm] = useState(emptyOfferProductForm);
        const [offerProductEditingId, setOfferProductEditingId] = useState('');
        const [offerProductSaving, setOfferProductSaving] = useState(false);
        const [offerProductNotice, setOfferProductNotice] = useState('');
        const [offerProductNoticeType, setOfferProductNoticeType] = useState('success');
        const [signalhireForm, setSignalhireForm] = useState(config.signalhireContactSearchSettings || {});
        const [signalhireSaving, setSignalhireSaving] = useState(false);
        const [signalhireMessage, setSignalhireMessage] = useState('');
        const [signalhireMessageType, setSignalhireMessageType] = useState('success');

        useEffect(function() {
            if (emailSubView === 'contacts' && !emailContactsHtml) {
                return;
            }

            if (emailSubView === 'campaign-message' && !emailCampaignMessageHtml) {
                return;
            }

            if (emailSubView === 'email-accounts' && !emailAccountsHtml) {
                return;
            }

            if (emailSubView === 'performance-review' && !emailPerformanceHtml) {
                return;
            }

            window.setTimeout(function() {
                document.dispatchEvent(new window.CustomEvent('smark:dashboard-embedded-view-loaded', {
                    detail: {
                        view: emailSubView,
                    },
                }));
            }, 0);
        }, [emailSubView, emailContactsHtml, emailCampaignMessageHtml, emailAccountsHtml, emailPerformanceHtml]);

        useEffect(function() {
            const handleEmbeddedViewLoad = function(event) {
                const detail = event.detail || {};
                if (detail.view === 'performance-review') {
                    loadEmailPerformanceView(detail.params || {});
                }
            };

            document.addEventListener('smark:dashboard-load-email-view', handleEmbeddedViewLoad);
            return function() {
                document.removeEventListener('smark:dashboard-load-email-view', handleEmbeddedViewLoad);
            };
        }, []);

        useEffect(function() {
            const handleModuleVisibilityUpdate = function(event) {
                const detail = event.detail || {};
                setModuleVisibility(detail.moduleVisibility || {});
            };

            document.addEventListener('smark:dashboard-module-visibility-updated', handleModuleVisibilityUpdate);
            return function() {
                document.removeEventListener('smark:dashboard-module-visibility-updated', handleModuleVisibilityUpdate);
            };
        }, []);

        useEffect(function() {
            const handleMarkBalanceUpdate = function(event) {
                const detail = event.detail || {};
                setMarkBalance(detail.markBalance || {});
            };

            document.addEventListener('smark:dashboard-mark-balance-updated', handleMarkBalanceUpdate);
            return function() {
                document.removeEventListener('smark:dashboard-mark-balance-updated', handleMarkBalanceUpdate);
            };
        }, []);

        useEffect(function() {
            if (active !== 'project-settings' || !projectSettingsHtml) {
                return;
            }

            window.setTimeout(function() {
                document.dispatchEvent(new window.CustomEvent('smark:project-settings-view-loaded'));
            }, 0);
        }, [active, projectSettingsHtml]);
        const mainItems = [
            { id: 'email', enabled: moduleVisibility.email !== false, label: text('emailMarketing', 'Email Marketing'), icon: h(SvgIcon, { path: icons.email, viewBox: '0 0 504 540' }) },
            { id: 'ads', enabled: moduleVisibility.ads !== false, label: text('ads', 'Ads'), inactive: true, icon: h(SvgIcon, { path: icons.ads, viewBox: '0 0 540 540' }) },
            { id: 'seo', enabled: moduleVisibility.seo !== false, label: text('seo', 'SEO'), href: urls.seo || '', icon: h(SvgIcon, { path: icons.seo, viewBox: '0 0 504 540' }) },
            { id: 'social', enabled: moduleVisibility.social !== false, label: text('social', 'Social Media'), href: urls.social || '', icon: h(SvgIcon, { path: icons.social, viewBox: '0 0 504 540' }) },
            { id: 'offer', enabled: moduleVisibility.offer !== false, label: text('offer', 'Offer'), icon: h(SvgIcon, { path: icons.offer, viewBox: '0 0 432 540' }) },
        ].filter(function(item) {
            return item.enabled;
        });
        const settingsItems = [
            { id: 'project-settings', label: textForLanguage(language, 'projectSettings', 'Project Settings'), view: 'project-settings' },
            { id: 'google-docs', label: textForLanguage(language, 'googleDocsConverter', 'Google Docs Converter'), href: urls.googleDocs || '' },
            { id: 'headline-analyzer', label: textForLanguage(language, 'headlineAnalyzer', 'Headline Analyzer'), href: urls.headlineAnalyzer || '' },
            { id: 'competitor-analysis', label: textForLanguage(language, 'competitorAnalysis', 'Competitor Analysis'), href: urls.competitorAnalysis || '' },
        ];
        const markBalanceLabel = markBalance.label || '—';

        const saveOfferSections = function(sections) {
            setOfferProductSaving(true);
            setOfferProductNotice('');

            const body = new window.URLSearchParams();
            body.append('action', 'smark_dashboard_offer_products_save');
            body.append('nonce', config.offerProductsNonce || '');
            body.append('sections', JSON.stringify(sections || {}));

            window.fetch(config.ajaxUrl || window.ajaxurl || '', {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
                },
                body: body.toString(),
            }).then(function(response) {
                return response.json();
            }).then(function(response) {
                if (response && response.success && response.data && response.data.sections && typeof response.data.sections === 'object') {
                    setOfferItemsBySection(response.data.sections);
                    setOfferProductNoticeType('success');
                    setOfferProductNotice(text('offerProductSaved', 'Products saved.'));
                    return;
                }

                setOfferProductNoticeType('error');
                setOfferProductNotice((response && response.data && response.data.message) || text('offerProductSaveError', 'Products could not be saved.'));
            }).catch(function() {
                setOfferProductNoticeType('error');
                setOfferProductNotice(text('offerProductSaveError', 'Products could not be saved.'));
            }).finally(function() {
                setOfferProductSaving(false);
            });
        };

        const updateOfferProductForm = function(key, value) {
            setOfferProductForm(Object.assign({}, offerProductForm, { [key]: value }));
            setOfferProductNotice('');
        };

        const changeOfferSection = function(sectionId) {
            setOfferActiveSection(sectionId);
            setOfferProductForm(emptyOfferProductForm);
            setOfferProductEditingId('');
            setOfferProductNotice('');
        };

        const submitOfferProduct = function() {
            const name = (offerProductForm.name || '').trim();
            if (!name) {
                setOfferProductNoticeType('error');
                setOfferProductNotice(offerActiveSection === 'product' ? text('offerProductNameRequired', 'Enter a product name.') : text('offerItemNameRequired', 'Enter a title.'));
                return;
            }

            const product = {
                id: offerProductEditingId || ('product-' + Date.now()),
                name: name,
                price: (offerProductForm.price || '').trim(),
                url: (offerProductForm.url || '').trim(),
                notes: (offerProductForm.notes || '').trim(),
            };
            const currentItems = Array.isArray(offerItemsBySection[offerActiveSection]) ? offerItemsBySection[offerActiveSection] : [];
            const nextProducts = offerProductEditingId
                ? currentItems.map(function(item) {
                    return item.id === offerProductEditingId ? product : item;
                })
                : currentItems.concat(product);
            const nextSections = Object.assign({}, offerItemsBySection, { [offerActiveSection]: nextProducts });

            setOfferProductForm(emptyOfferProductForm);
            setOfferProductEditingId('');
            setOfferItemsBySection(nextSections);
            saveOfferSections(nextSections);
        };

        const editOfferProduct = function(product) {
            setOfferProductEditingId(product.id || '');
            setOfferProductForm({
                name: product.name || '',
                price: product.price || '',
                url: product.url || '',
                notes: product.notes || '',
            });
            setOfferProductNotice('');
        };

        const deleteOfferProduct = function(id) {
            const currentItems = Array.isArray(offerItemsBySection[offerActiveSection]) ? offerItemsBySection[offerActiveSection] : [];
            const nextProducts = currentItems.filter(function(product) {
                return product.id !== id;
            });
            const nextSections = Object.assign({}, offerItemsBySection, { [offerActiveSection]: nextProducts });
            if (offerProductEditingId === id) {
                setOfferProductEditingId('');
                setOfferProductForm(emptyOfferProductForm);
            }
            setOfferItemsBySection(nextSections);
            saveOfferSections(nextSections);
        };

        const setActiveItem = function(id) {
            setActive(id);
            if (id === 'email') {
                setEmailSubView('workflow');
            }
            if (id !== 'project-settings') {
                setProjectSettingsError('');
            }
            if (id !== 'language') {
                setLanguageOpen(false);
            }
            setMarkOpen(false);
            if (id !== 'settings') {
                setSettingsOpen(false);
            }
        };

        const openSettingsView = function(view) {
            setSettingsOpen(false);
            setLanguageOpen(false);
            setMarkOpen(false);

            if (view !== 'project-settings') {
                return;
            }

            setActive('project-settings');
            setProjectSettingsError('');

            if (projectSettingsHtml) {
                return;
            }

            setProjectSettingsLoading(true);

            loadEmailEmbeddedView('smark_dashboard_project_settings_view', config.projectSettingsViewNonce || '', {}, function(html) {
                setProjectSettingsHtml(html);
            }, function(message) {
                setProjectSettingsError(message);
            }, function() {
                setProjectSettingsLoading(false);
            });
        };

        const openEmailSubView = function(view, params) {
            if (view !== 'contacts' && view !== 'campaign-message' && view !== 'email-accounts' && view !== 'performance-review' && view !== 'signalhire-contact-search') {
                setEmailSubView('workflow');
                return;
            }

            setEmailSubView(view);

            if (view === 'signalhire-contact-search') {
                setSignalhireMessage('');
                return;
            }

            if (view === 'contacts') {
                setEmailContactsError('');

                if (emailContactsHtml) {
                    return;
                }

                setEmailContactsLoading(true);

                loadEmailEmbeddedView('smark_dashboard_email_contacts_view', config.emailContactsViewNonce || '', params || {}, function(html) {
                    setEmailContactsHtml(html);
                }, function(message) {
                    setEmailContactsError(message);
                }, function() {
                    setEmailContactsLoading(false);
                });

                return;
            }

            if (view === 'email-accounts') {
                setEmailAccountsError('');

                if (emailAccountsHtml) {
                    return;
                }

                setEmailAccountsLoading(true);

                loadEmailEmbeddedView('smark_dashboard_email_accounts_view', config.emailAccountsViewNonce || '', {}, function(html) {
                    setEmailAccountsHtml(html);
                }, function(message) {
                    setEmailAccountsError(message);
                }, function() {
                    setEmailAccountsLoading(false);
                });

                return;
            }

            if (view === 'performance-review') {
                setEmailPerformanceError('');

                if (emailPerformanceHtml) {
                    return;
                }

                loadEmailPerformanceView({});

                return;
            }

            setEmailCampaignMessageError('');

            if (emailCampaignMessageHtml) {
                return;
            }

            setEmailCampaignMessageLoading(true);

            loadEmailEmbeddedView('smark_dashboard_email_campaign_message_view', config.emailCampaignMessageViewNonce || '', {}, function(html) {
                setEmailCampaignMessageHtml(html);
            }, function(message) {
                setEmailCampaignMessageError(message);
            }, function() {
                setEmailCampaignMessageLoading(false);
            });
        };

        const openEmailSubViewFromDashboard = function(view, params) {
            setActive('email');
            setLanguageOpen(false);
            setMarkOpen(false);
            setSettingsOpen(false);
            openEmailSubView(view, params);
        };

        const openDailyGuideSmartAction = function(card) {
            const key = card && card.key ? String(card.key) : '';
            if (!key) {
                return;
            }

            if (key === 'email_contacts_daily') {
                setActive('email');
                setLanguageOpen(false);
                setMarkOpen(false);
                setSettingsOpen(false);
                openEmailSubView('signalhire-contact-search');
                setSignalhireMessage('');
                return;
            }

            document.dispatchEvent(new window.CustomEvent('smark:daily-guide-smart-action', {
                detail: {
                    key: key,
                },
            }));
        };

        useEffect(function() {
            const openSignalHirePanel = function() {
                openDailyGuideSmartAction({ key: 'email_contacts_daily' });
            };

            const handleSignalHireSmartClick = function(event) {
                const target = event.target && typeof event.target.closest === 'function'
                    ? event.target.closest('[data-smark-daily-guide-key="email_contacts_daily"]')
                    : null;

                if (!target || target.disabled || target.getAttribute('aria-disabled') === 'true') {
                    return;
                }

                event.preventDefault();
                event.stopPropagation();
                if (typeof event.stopImmediatePropagation === 'function') {
                    event.stopImmediatePropagation();
                }
                openSignalHirePanel();
            };

            document.addEventListener('smark:open-signalhire-contact-search', openSignalHirePanel);
            document.addEventListener('click', handleSignalHireSmartClick, true);
            return function() {
                document.removeEventListener('smark:open-signalhire-contact-search', openSignalHirePanel);
                document.removeEventListener('click', handleSignalHireSmartClick, true);
            };
        }, []);

        const updateSignalhireForm = function(key, value) {
            setSignalhireForm(Object.assign({}, signalhireForm, { [key]: value }));
            setSignalhireMessage('');
        };

        const saveSignalhireSettings = function() {
            const nextSettings = Object.assign({}, signalhireForm);
            const hasValue = Object.keys(nextSettings).some(function(key) {
                return String(nextSettings[key] || '').trim() !== '';
            });

            if (!hasValue) {
                setSignalhireMessageType('error');
                setSignalhireMessage(text('signalhireValidation', 'Fill at least one field.'));
                return;
            }

            setSignalhireSaving(true);
            setSignalhireMessage('');

            const body = new window.URLSearchParams();
            body.append('action', 'smark_save_signalhire_contact_search_settings');
            body.append('nonce', config.signalhireSettingsNonce || '');
            body.append('settings', JSON.stringify(nextSettings));

            window.fetch(config.ajaxUrl || window.ajaxurl || '', {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
                },
                body: body.toString(),
            }).then(function(response) {
                return response.json();
            }).then(function(response) {
                if (response && response.success && response.data && response.data.settings) {
                    setSignalhireForm(response.data.settings);
                    setSignalhireMessageType('success');
                    setSignalhireMessage(text('signalhireSaved', 'Search settings saved.'));
                    return;
                }

                setSignalhireMessageType('error');
                setSignalhireMessage((response && response.data && response.data.message) || text('smartError', 'Agent action failed. Please try again.'));
            }).catch(function() {
                setSignalhireMessageType('error');
                setSignalhireMessage(text('smartError', 'Agent action failed. Please try again.'));
            }).finally(function() {
                setSignalhireSaving(false);
            });
        };

        useEffect(function() {
            const params = new window.URLSearchParams(window.location.search || '');
            const initialEmailView = params.get('smark_email_view') || '';
            if (initialEmailView) {
                openEmailSubViewFromDashboard(initialEmailView, {
                    smark_message: params.get('smark_message') || '',
                    import_token: params.get('import_token') || '',
                });
            }
        }, []);

        const loadEmailPerformanceView = function(params) {
            setEmailSubView('performance-review');
            setEmailPerformanceError('');
            setEmailPerformanceLoading(true);

            loadEmailEmbeddedView('smark_dashboard_email_performance_view', config.emailPerformanceViewNonce || '', params || {}, function(html) {
                setEmailPerformanceHtml(html);
            }, function(message) {
                setEmailPerformanceError(message);
            }, function() {
                setEmailPerformanceLoading(false);
            });
        };

        const loadEmailEmbeddedView = function(action, nonce, params, onSuccess, onError, onComplete) {
            const body = new window.URLSearchParams();
            body.append('action', action);
            body.append('nonce', nonce);
            Object.keys(params || {}).forEach(function(key) {
                body.append(key, params[key]);
            });

            window.fetch(config.ajaxUrl || window.ajaxurl || '', {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
                },
                body: body.toString(),
            }).then(function(response) {
                return response.json();
            }).then(function(response) {
                if (response && response.success && response.data && response.data.html) {
                    onSuccess(response.data.html);
                    return;
                }

                onError((response && response.data && response.data.message) || text('loadError', 'Unable to load this section.'));
            }).catch(function() {
                onError(text('loadError', 'Unable to load this section.'));
            }).finally(function() {
                onComplete();
            });
        };

        return h('main', { className: 'smark-dashboard-app-canvas smark-dashboard-app-canvas--' + (language === 'fa' ? 'rtl' : 'ltr'), 'aria-label': text('workspace', 'SMark dashboard workspace') },
            active === 'smark' ? h('div', { className: 'smark-dashboard-workspace-content' },
                h('div', { key: 'smark', className: 'smark-dashboard-view' },
                    h(DailyGuideGrid, { language: language, moduleVisibility: moduleVisibility, onOpenEmailView: openEmailSubViewFromDashboard, onSmartAction: openDailyGuideSmartAction })
                )
            ) : null,
            active === 'email' ? h('div', { className: 'smark-dashboard-workspace-content' },
                emailSubView === 'contacts'
                    ? h('section', { key: 'email-contacts', className: 'smark-dashboard-embedded-view smark-dashboard-view' },
                        emailContactsLoading ? h('div', { className: 'smark-dashboard-embedded-view__state' }, text('loading', 'Loading...')) : null,
                        emailContactsError ? h('div', { className: 'smark-dashboard-embedded-view__state smark-dashboard-embedded-view__state--error' }, emailContactsError) : null,
                        emailContactsHtml ? h('div', { className: 'smark-dashboard-embedded-view__content', dangerouslySetInnerHTML: { __html: emailContactsHtml } }) : null
                    )
                    : emailSubView === 'campaign-message'
                    ? h('section', { key: 'email-campaign-message', className: 'smark-dashboard-embedded-view smark-dashboard-view' },
                        emailCampaignMessageLoading ? h('div', { className: 'smark-dashboard-embedded-view__state' }, text('loading', 'Loading...')) : null,
                        emailCampaignMessageError ? h('div', { className: 'smark-dashboard-embedded-view__state smark-dashboard-embedded-view__state--error' }, emailCampaignMessageError) : null,
                        emailCampaignMessageHtml ? h('div', { className: 'smark-dashboard-embedded-view__content', dangerouslySetInnerHTML: { __html: emailCampaignMessageHtml } }) : null
                    )
                    : emailSubView === 'email-accounts'
                    ? h('section', { key: 'email-accounts', className: 'smark-dashboard-embedded-view smark-dashboard-view' },
                        emailAccountsLoading ? h('div', { className: 'smark-dashboard-embedded-view__state' }, text('loading', 'Loading...')) : null,
                        emailAccountsError ? h('div', { className: 'smark-dashboard-embedded-view__state smark-dashboard-embedded-view__state--error' }, emailAccountsError) : null,
                        emailAccountsHtml ? h('div', { className: 'smark-dashboard-embedded-view__content', dangerouslySetInnerHTML: { __html: emailAccountsHtml } }) : null
                    )
                    : emailSubView === 'performance-review'
                    ? h('section', { key: 'performance-review', className: 'smark-dashboard-embedded-view smark-dashboard-view' },
                        emailPerformanceLoading ? h('div', { className: 'smark-dashboard-embedded-view__state' }, text('loading', 'Loading...')) : null,
                        emailPerformanceError ? h('div', { className: 'smark-dashboard-embedded-view__state smark-dashboard-embedded-view__state--error' }, emailPerformanceError) : null,
                        emailPerformanceHtml ? h('div', { className: 'smark-dashboard-embedded-view__content', dangerouslySetInnerHTML: { __html: emailPerformanceHtml } }) : null
                    )
                    : emailSubView === 'signalhire-contact-search'
                    ? h(SignalHireSettingsPanel, {
                        key: 'signalhire-contact-search',
                        form: signalhireForm,
                        saving: signalhireSaving,
                        message: signalhireMessage,
                        messageType: signalhireMessageType,
                        onChange: updateSignalhireForm,
                        onSubmit: saveSignalhireSettings,
                        onClose: function() {
                            setActive('smark');
                            setEmailSubView('workflow');
                            setSignalhireMessage('');
                        },
                    })
                    : h(EmailWorkflowPanel, { key: 'email', language: language, onOpenView: openEmailSubView })
            ) : null,
            active === 'offer' ? h('div', { className: 'smark-dashboard-workspace-content' },
                h(OfferPanel, {
                    key: 'offer',
                    sections: offerSections,
                    activeSection: offerActiveSection,
                    itemsBySection: offerItemsBySection,
                    form: offerProductForm,
                    editingId: offerProductEditingId,
                    saving: offerProductSaving,
                    notice: offerProductNotice,
                    noticeType: offerProductNoticeType,
                    onSectionChange: changeOfferSection,
                    onFormChange: updateOfferProductForm,
                    onSubmit: submitOfferProduct,
                    onEdit: editOfferProduct,
                    onDelete: deleteOfferProduct,
                    onCancelEdit: function() {
                        setOfferProductEditingId('');
                        setOfferProductForm(emptyOfferProductForm);
                        setOfferProductNotice('');
                    },
                })
            ) : null,
            active === 'project-settings' ? h('div', { className: 'smark-dashboard-workspace-content' },
                h('section', { key: 'project-settings', className: 'smark-dashboard-embedded-view smark-dashboard-view' },
                    projectSettingsLoading ? h('div', { className: 'smark-dashboard-embedded-view__state' }, text('loading', 'Loading...')) : null,
                    projectSettingsError ? h('div', { className: 'smark-dashboard-embedded-view__state smark-dashboard-embedded-view__state--error' }, projectSettingsError) : null,
                    projectSettingsHtml ? h('div', { className: 'smark-dashboard-embedded-view__content', dangerouslySetInnerHTML: { __html: projectSettingsHtml } }) : null
                )
            ) : null,
            h('nav', { className: 'smark-dashboard-glass-menu', 'aria-label': text('navigation', 'SMark dashboard navigation') },
                h(MenuItem, {
                    label: text('smark', 'SMark'),
                    tooltipMeta: config.version ? 'v' + config.version : '',
                    active: active === 'smark',
                    onClick: function() { setActiveItem('smark'); },
                }, h('span', {
                    className: 'smark-dashboard-glass-menu__logo',
                    'aria-hidden': true,
                    style: {
                        WebkitMaskImage: logoUrl ? 'url("' + logoUrl + '")' : undefined,
                        maskImage: logoUrl ? 'url("' + logoUrl + '")' : undefined,
                    },
                })),
                h('div', { className: 'smark-dashboard-glass-menu__group' },
                    mainItems.map(function(item) {
                        return h(MenuItem, {
                            key: item.id,
                            label: item.label,
                            href: item.href,
                            active: !item.inactive && active === item.id,
                            onClick: item.href ? undefined : item.inactive ? function() {} : function() { setActiveItem(item.id); },
                        }, item.icon);
                    })
                ),
                h('div', { className: 'smark-dashboard-glass-menu__group smark-dashboard-glass-menu__group--bottom' },
                    h('div', { className: 'smark-dashboard-glass-menu__mark-wrap' + (markOpen ? ' is-open' : '') },
                        h(MenuItem, {
                            label: text('markCredit', 'Mark'),
                            active: false,
                            expanded: markOpen,
                            onClick: function() {
                                setMarkOpen(!markOpen);
                                setLanguageOpen(false);
                                setSettingsOpen(false);
                            },
                        }, h(SvgIcon, { path: icons.creditCard, viewBox: '0 0 504 540' })),
                        h('div', { className: 'smark-dashboard-glass-menu__mark-panel', role: 'status' },
                            h('span', { className: 'smark-dashboard-glass-menu__mark-label' }, text('markCreditBalance', 'Mark Credit Balance')),
                            h('strong', { className: 'smark-dashboard-glass-menu__mark-value' }, markBalanceLabel)
                        )
                    ),
                    h('div', { className: 'smark-dashboard-glass-menu__language-wrap' + (languageOpen ? ' is-open' : '') },
                        h(MenuItem, {
                            label: text('language', 'Language'),
                            active: false,
                            expanded: languageOpen,
                            onClick: function() {
                                setLanguageOpen(!languageOpen);
                                setMarkOpen(false);
                                setSettingsOpen(false);
                            },
                        }, h(SvgIcon, { path: icons.language, viewBox: '0 0 504 540' })),
                        h('div', { className: 'smark-dashboard-glass-menu__language-panel', role: 'menu', 'aria-label': text('chooseLanguage', 'Choose language') },
                            [
                                { id: 'fa', label: text('persian', 'Persian') },
                                { id: 'en', label: text('english', 'English') },
                            ].map(function(option) {
                                const selected = language === option.id;
                                return h('button', {
                                    key: option.id,
                                    type: 'button',
                                    className: 'smark-dashboard-glass-menu__language-option' + (selected ? ' is-selected' : ''),
                                    role: 'menuitemradio',
                                    'aria-checked': selected,
                                    onClick: function(event) {
                                        event.stopPropagation();
                                        setLanguage(option.id);
                                        setLanguageOpen(false);
                                    },
                                }, option.label);
                            })
                        )
                    ),
                    h('div', { className: 'smark-dashboard-glass-menu__settings-wrap' + (settingsOpen ? ' is-open' : '') },
                        h(MenuItem, {
                            label: text('settings', 'Settings'),
                            active: active === 'project-settings',
                            expanded: settingsOpen,
                            onClick: function() {
                                setSettingsOpen(!settingsOpen);
                                setMarkOpen(false);
                                setLanguageOpen(false);
                            },
                        }, h(SvgIcon, { path: icons.settings, viewBox: '0 0 468 540' })),
                        h('div', { className: 'smark-dashboard-glass-menu__settings-panel', role: 'menu', 'aria-label': text('settings', 'Settings') },
                            settingsItems.map(function(option) {
                                const commonProps = {
                                    key: option.id,
                                    className: 'smark-dashboard-glass-menu__language-option',
                                    role: 'menuitem',
                                };

                                if (option.view) {
                                    return h('button', Object.assign({}, commonProps, {
                                        type: 'button',
                                        onClick: function(event) {
                                            event.preventDefault();
                                            event.stopPropagation();
                                            openSettingsView(option.view);
                                        },
                                    }), option.label);
                                }

                                return h('a', Object.assign({}, commonProps, {
                                    href: option.href || '#',
                                }), option.label);
                            })
                        )
                    )
                )
            )
        );
    }

    const root = document.getElementById('smark-dashboard-root');
    if (!root) {
        return;
    }

    if (wp.element.createRoot) {
        wp.element.createRoot(root).render(h(DashboardApp));
    } else {
        wp.element.render(h(DashboardApp), root);
    }
}(window.wp, window, document));
