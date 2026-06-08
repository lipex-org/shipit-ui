import './main.entrypoint.css';
import * as Turbo from "@hotwired/turbo";

// Expose Turbo to window for other entrypoints
(window as any).Turbo = Turbo;

console.log('ShipIt UI - Main Entrypoint Loaded with Turbo');
