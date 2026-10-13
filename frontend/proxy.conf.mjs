// Dev server proxy: the browser only talks to the dev server, so the Mercure
// authorization cookie set by the API is sent to the hub as well.
const api = process.env['API_URL'] ?? 'http://127.0.0.1:8000';
const mercure = process.env['MERCURE_HUB_URL'] ?? 'http://localhost:3000';

// Path under which the app is served: "/" by default, "/absproxy/4200/" behind
// code-server (see the "code-server" configuration in angular.json)
const prefixes = ['', '/absproxy/4200'];

export default Object.fromEntries(
  prefixes.flatMap((prefix) => [
    // Keep the original Host header: Symfony derives the Mercure cookie domain from it
    [
      `${prefix}/api`,
      {
        target: api,
        secure: false,
        changeOrigin: false,
        rewrite: (path) => path.slice(prefix.length),
      },
    ],
    [
      `${prefix}/.well-known/mercure`,
      {
        target: mercure,
        secure: false,
        changeOrigin: false,
        rewrite: (path) => path.slice(prefix.length),
      },
    ],
  ]),
);
