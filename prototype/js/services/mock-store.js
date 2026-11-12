import {seed} from '../data/fixtures.js';
const KEY='pettel-prototype-v1';
const clone=x=>JSON.parse(JSON.stringify(x));
const normalize=s=>{
 const base=clone(seed),value={...base,...s};
 value.ui={...base.ui,...(s?.ui||{}),filters:{...base.ui.filters,...(s?.ui?.filters||{})}};
 value.adminOperations=Array.isArray(s?.adminOperations)?s.adminOperations:base.adminOperations;
 value.adminAudit=Array.isArray(s?.adminAudit)?s.adminAudit:[];
 return value;
};
export function loadState(){try{const s=localStorage.getItem(KEY);if(s){const value=JSON.parse(s);if(value?.version===1)return normalize(value.state||{});}}catch{}return clone(seed);}
let state=loadState();
export const getState=()=>state;
export function save(){try{localStorage.setItem(KEY,JSON.stringify({version:1,state}));}catch{}}
export function update(fn){const result=fn(state);save();return result;}
export function reset(){state=clone(seed);save();return state;}
export function setPersona(persona){if(!['guest','owner','caregiver','admin'].includes(persona))return;state.persona=persona;save();}
export function replaceState(next){state=next;save();}
