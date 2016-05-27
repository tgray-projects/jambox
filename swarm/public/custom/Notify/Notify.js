/**
 * Created by tgray on 2/5/2014.
 */
$(document).ready( function() {
    swarm.notify.addNotifyLink();

    $(document).on('swarm-login', function() {
        swarm.notify.addNotifyLink();
    });
});

swarm.notify = {
    addNotifyLink: function() {
        $('body.authenticated ul.user li.dropdown ul.dropdown-menu').append(
            '<li><a href="/contact/" onclick="swarm.notify.contact();'
                + ' return false;">Workshop Support</a></li>');
    },

    contact: function() {
        if ($('.contact-dialog.modal').length) {
            swarm.form.clearErrors('.contact-dialog.modal form', true);
            swarm.modal.show('.contact-dialog.modal');
            return;
        }

        $.ajax({url: swarm.url('/contact/'), data: {format: 'partial'}}).done(function(data) {
            $('body').append(data);
        });
        swarm.modal.show('.contact-dialog.modal');
    }
};