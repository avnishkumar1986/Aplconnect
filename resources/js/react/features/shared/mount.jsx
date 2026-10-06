import { createRoot } from 'react-dom/client';
export function mountForm(name,Component){const root=document.getElementById(`react-${name}-form`),data=document.getElementById(`react-${name}-form-props`);if(!root||!data||root.dataset.mounted)return;createRoot(root).render(<Component {...JSON.parse(data.textContent||'{}')}/>);root.dataset.mounted='true';}
