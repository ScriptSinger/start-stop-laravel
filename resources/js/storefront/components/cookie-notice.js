/*
 * Плашка про cookie (uni_notification темы): согласие помним в cookie
 * notificationOffTime, как старый сайт.
 */
export default ({rememberHours}) => ({
    visible: !document.cookie.includes('notificationOffTime'),

    accept() {
        document.cookie = `notificationOffTime=1; path=/; max-age=${rememberHours * 3600}; SameSite=Lax`;
        this.visible = false;
    },
});
