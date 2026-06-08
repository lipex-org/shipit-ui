# @jengo/vite

A Vite plugin designed for seamless integration with the Jengo CodeIgniter 4 framework.

## Features

- **Automatic Entrypoint Discovery**: Automatically finds `*.entrypoint.ts`, `*.entrypoint.js`, `*.entrypoint.css`, and `*.entrypoint.scss` files in your `app` directory.
- **Dynamic Configuration**: Configures Vite's `rollupOptions.input` automatically based on discovered entrypoints.
- **Smart Defaults**: Sets `build.outDir` to `public/dist` and enables `build.manifest` by default.

## Installation

```bash
npm install @jengo/vite --save-dev
```

## Usage

In your `vite.config.js`:

```javascript
import { defineConfig } from 'vite';
import jengo from '@jengo/vite';

export default defineConfig({
    plugins: [
        jengo(),
    ],
});
```

## Configuration

The plugin works out of the box with zero configuration. However, it respects your manual Vite configuration if you choose to override defaults.

- **Entrypoints**: The plugin executes `php spark vite:config` to discover entrypoints. Ensure your Jengo CLI is working.
- **Output Directory**: Defaults to `public/dist`. You can override this in your `vite.config.js` `build.outDir`.
- **Manifest**: Defaults to `true`. You can override this in your `vite.config.js` `build.manifest`.

## License

MIT
