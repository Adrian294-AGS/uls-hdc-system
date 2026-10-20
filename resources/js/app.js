import Alpine from 'alpinejs';
import { registerForms } from './forms';

window.Alpine = Alpine;

registerForms(Alpine);

Alpine.start();
