import './bootstrap';

// Bootstrap 5 JS (dropdowns, modals, the navbar toggler, etc.). Bundled here
// rather than loaded from a CDN — see resources/scss/app.scss.
import 'bootstrap';

import { initStudentSelector } from './studentSelector';

// The attendance and book-distribution forms each render a selection input plus
// a grid of student buttons; initStudentSelector is a no-op on pages without
// them.
document.addEventListener('DOMContentLoaded', () => initStudentSelector());
