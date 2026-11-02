import{j as t}from"./app-DOAS_B2b.js";import{c as o}from"./brand-lockup-CNk_mdUd.js";/**
 * @license lucide-react v0.475.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const p=[["path",{d:"M12 5v14",key:"s699le"}],["path",{d:"m19 12-7 7-7-7",key:"1idqje"}]],l=o("ArrowDown",p);/**
 * @license lucide-react v0.475.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const h=[["path",{d:"m21 16-4 4-4-4",key:"f6ql7i"}],["path",{d:"M17 20V4",key:"1ejh1v"}],["path",{d:"m3 8 4-4 4 4",key:"11wl7u"}],["path",{d:"M7 4v16",key:"1glfcx"}]],x=o("ArrowUpDown",h);/**
 * @license lucide-react v0.475.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const m=[["path",{d:"m5 12 7-7 7 7",key:"hav0vg"}],["path",{d:"M12 19V5",key:"x0mq9r"}]],w=o("ArrowUp",m);function g({label:a,column:n,activeColumn:r,direction:s,onSort:c,className:i=""}){const e=r===n,d=e?s==="asc"?w:l:x;return t.jsx("th",{className:i,children:t.jsxs("button",{type:"button",onClick:()=>c(n),className:`group inline-flex items-center gap-1.5 font-semibold tracking-wide hover:text-slate-900 ${e?"text-slate-800":""}`,"aria-label":`Sort by ${a}`,"aria-sort":e?s==="asc"?"ascending":"descending":"none",children:[t.jsx("span",{children:a}),t.jsx(d,{className:`size-3.5 ${e?"text-slate-700":"text-slate-300 group-hover:text-slate-500"}`})]})})}export{g as D};
