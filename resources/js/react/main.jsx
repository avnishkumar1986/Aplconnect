import React from 'react';
import { createRoot } from 'react-dom/client';
import Dashboard from './Dashboard';
import { mountUserForm } from './features/users';
import { mountProjectForms } from './features';

const dashboardRoot = document.getElementById('dashboard-root');
if (dashboardRoot) {
    createRoot(dashboardRoot).render(<Dashboard stats={JSON.parse(dashboardRoot.dataset.stats)} user={dashboardRoot.dataset.user} />);
}

window.mountAdminForms = () => {
    mountUserForm();
    mountProjectForms();
};

window.mountAdminForms();
