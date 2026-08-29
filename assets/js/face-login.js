// Entry for the face login page.
//
// The security pages load only the `login` bundle, which does not boot Stimulus.
// Rather than pull the whole admin controller set onto a public page, this entry
// starts a minimal Stimulus application with the one controller it needs.
import { Application } from '@hotwired/stimulus';
import FaceLoginController from '../controllers/face_login_controller';

const application = Application.start();
application.register('face-login', FaceLoginController);
