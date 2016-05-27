$(document).ready( function() {
    var login = $('body.anonymous .navbar ul.nav.user li a');
    login.text(login.text() + ' / Sign Up');

    $(document).on('click', '.login-dialog form button.signup', function() {
        $('.login-dialog.modal').modal('hide');
        swarm.accounts.signupDialog();
    });

    var user = swarm.user.getAuthenticatedUser();
    var userProfileId = $('div.profile-sidebar.user-sidebar div.profile-info div.title h4').text();

    // nothing to do if not authenticated
    if (!user || user.id != userProfileId) {
        return;
    }

    var resetButton = '<div class="privileged pad2"><button type="button" '
                    + 'class="btn btn-primary btn-block" '
                    + 'onclick="swarm.accounts.changePassword(\'' +  user.id + '\');">'
                    + 'Change My Password</button></div>';
    $('div.profile-sidebar.user-sidebar div.profile-info div.body div.metrics').before(resetButton);
});

swarm.accounts = {
    signupDialog: function() {
        if ($('.signup-dialog.modal').length) {
            swarm.form.clearErrors('.signup-dialog.modal form', true);
            swarm.modal.show('.signup-dialog.modal');
            return;
        }

        $.ajax({url: swarm.url('/signup'), data: {format: 'partial'}}).done(function(data) {
            $('body').append(data);
            $('.signup-dialog form').submit(
                function(e) {
                    swarm.accounts.signup(e)
                }
            );
        });
    },

    signup: function(e) {
        e.preventDefault();
        swarm.form.post(swarm.url('/signup/?format=partial'), '.signup-dialog form', function(response){
            if (response.isValid) {
                // if user is present, we're skipping email authentication and they're already
                // signed in - reload the page
                if (response.user) {
                    window.location.reload();
                } else {
                    $('.signup-dialog').modal('hide');
                    swarm.workshop.notification(response.message, 'success');
                }
            }
        }, '.signup-dialog .modal-body');
    },

    changePassword: function(userId) {
        if ($('.change-password-dialog.modal').length) {
            swarm.form.clearErrors('.change-password-dialog.modal form', true);
            swarm.modal.show('.change-password-dialog.modal');
            return;
        }

        $.ajax({url: swarm.url('/account/password/change/') + userId, data: {format: 'partial'}}).done(function(data) {
            $('body').append(data);
        });
        swarm.modal.show('.change-password-dialog.modal');
    },

    deleteUser: function(userId) {
        if ($('.delete-user-dialog.modal').length) {
            swarm.form.clearErrors('.delete-user-dialog.modal form', true);
            swarm.modal.show('.delete-user-dialog.modal');
            return;
        }

        $.ajax({
            url: swarm.url('/account/delete/') + userId,
            data: {format: 'partial'},
        }).done(function(data) {
            $('body').append(data);
        });
        swarm.modal.show('.delete-user-dialog.modal');

    }
};