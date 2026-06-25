import './bootstrap';
import { initStudentSelector } from './studentSelector';

// The attendance and book-distribution forms each render a selection input plus
// a grid of student buttons; initStudentSelector is a no-op on pages without
// them.
document.addEventListener('DOMContentLoaded', () => initStudentSelector());
