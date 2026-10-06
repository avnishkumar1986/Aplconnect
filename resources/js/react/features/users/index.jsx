import UserForm from './UserForm';
import { mountForm } from '../shared/mount';

export function mountUserForm() {
    mountForm('user', UserForm);
}

export { default as UserForm } from './UserForm';
