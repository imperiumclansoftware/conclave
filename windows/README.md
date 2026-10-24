# Conclave pour Windows

Application Windows 11 (et Windows 10 2004 ou plus récent) de Conclave : **C# / WinUI 3** (Windows App SDK),
qui affiche le Conclave du clan dans WebView2 et y ajoute ce qu'un onglet de navigateur ne sait pas faire :

- **notifications Windows** (événements sur vos jeux, messages reçus quand la fenêtre n'est pas au premier
  plan), même sur un serveur en `http://`, et un clic ouvre la bonne page ;
- **zone de notification** : fermer la fenêtre garde Conclave actif pour continuer à prévenir (désactivable) ;
- **lancement au démarrage de Windows**, dans la zone de notification ;
- **pastille** des messages non lus sur l'icône de la barre des tâches ;
- une seule instance : relancer Conclave ramène la fenêtre existante ;
- **mises à jour** : un bandeau propose la version qui va avec le serveur du clan.

Au premier lancement, l'application demande l'adresse du serveur (celle tapée dans le navigateur, par ex.
`http://192.168.1.10:8080`) et vérifie que c'est bien un serveur Conclave.

## Organisation

| Dossier | Contenu |
|---|---|
| `src/Conclave.Desktop` | L'application WinUI 3 (interface écrite en C#, sans XAML) |
| `src/Conclave.Desktop.Core` | Ce qu'elle décide sans Windows : adresse du serveur, liens autorisés, messages de la page, réglages, versions |
| `tests/Conclave.Desktop.Core.Tests` | Tests (xUnit v3), exécutables sous Windows, Linux ou macOS |
| `installer/Conclave.iss` | Installateur Inno Setup : par utilisateur, sans droits administrateur, en français |

L'application n'est pas empaquetée en MSIX : elle est *self-contained* (.NET et Windows App SDK inclus), donc rien
à installer à côté et aucun certificat à faire approuver. Les réglages et la session sont dans
`%LOCALAPPDATA%\Conclave` (avec un journal `conclave.log`).

### Dialogue avec la page

La page Conclave détecte l'application grâce à `window.conclaveDesktop` (injecté avant son chargement) et lui
écrit par `window.chrome.webview.postMessage` (voir `frontend/src/app/core/desktop/desktop-bridge.service.ts`) :

- `{"type":"notification","title","body","tag","link"}` : notification Windows ; `link` est un chemin du serveur ;
- `{"type":"badge","count"}` : messages non lus.

L'application répond `{"type":"navigate","link"}` quand on clique une notification. Seules les pages du serveur
sont acceptées dans un sens comme dans l'autre ; les autres liens s'ouvrent dans le navigateur.

## Construire

### Par GitHub Actions

Le workflow `.github/workflows/windows.yml` (runner Windows) exécute les tests, publie l'application et
construit `Conclave-Setup-<version>.exe` :

- lancé à la main (onglet *Actions*) : l'installateur est un artefact de l'exécution ;
- sur un tag `v0.4.0` : l'installateur est joint à la release GitHub de cette version.

### Sur un PC Windows

Prérequis : SDK .NET 10 et [Inno Setup 6](https://jrsoftware.org/isinfo.php) (ou Visual Studio 2022/2026 avec la
charge « Développement d'applications Windows » pour déboguer).

```powershell
cd windows
dotnet test --project tests/Conclave.Desktop.Core.Tests
dotnet publish src/Conclave.Desktop -c Release -r win-x64 -p:Version=0.4.0 -o publish
iscc /DAppVersion=0.4.0 installer/Conclave.iss    # → dist/Conclave-Setup-0.4.0.exe
```

### Vérifier la compilation sous Linux

Le code se compile aussi sous Linux (sans produire d'application utilisable : l'empaquetage demande des outils
Windows). `mt-linux-check` remplace `mt.exe` en copiant le manifeste :

```sh
dotnet test --project tests/Conclave.Desktop.Core.Tests
dotnet build src/Conclave.Desktop -c Release -p:ManifestTool=/usr/local/bin/mt-linux-check -p:AppxGeneratePriEnabled=false
```

## Diffusion

- **Depuis Conclave** : *menu du compte > Application Windows* télécharge l'installateur par
  `GET /api/desktop/windows/download`. Le serveur sert le plus récent `Conclave-Setup-x.y.z.exe` déposé dans son
  dossier de téléchargements (`/data/downloads` dans Docker), sinon redirige vers la release GitHub de sa version.
- **GitHub Releases** : l'installateur est joint à chaque release (`v*`).

L'installateur n'est pas signé : Windows SmartScreen affiche « Windows a protégé votre ordinateur » au premier
lancement (*Informations complémentaires > Exécuter quand même*). Un certificat de signature de code supprimerait
cet avertissement.
