(() => {
  const lang = document.documentElement.lang.toLowerCase();
  const isEnglish = lang.startsWith('en');
  const isFrench = lang.startsWith('fr');
  if (!isEnglish && !isFrench) return;

  const exact = new Map(Object.entries({
    'Papierkorb':'Trash',
    'Eigenes Album':'Own album',
    'Fremdes Album':'Other user’s album',
    'Albumtitel':'Album title',
    'Erscheinungsjahr':'Release year',
    'Beschreibung':'Description',
    'Cover löschen':'Delete cover',
    'Titel':'Track',
    'Titel hochladen':'Upload tracks',
    'Neue Titel hochladen':'Upload new tracks',
    'Bereit':'Ready',
    'Fehler':'Error',
    'Hinweis':'Notice',
    'Bestätigen':'Confirm',
    'Abbrechen':'Cancel',
    'Löschen':'Delete',
    'Schließen':'Close',
    'Speichern':'Save',
    'Wiederherstellen':'Restore',
    'Endgültig löschen':'Delete permanently',
    'Alle Titel auswählen':'Select all tracks',
    'Ausgewählte löschen':'Delete selected',
    'CD hinzufügen':'Add disc',
    'Albumtitel übernehmen?':'Use album title?',
    'Titel ersetzen':'Replace title',
    'Cover übernehmen?':'Use cover?',
    'Cover übernehmen':'Use cover',
    'Titel wirklich löschen?':'Delete this track?',
    'Titel löschen':'Delete tracks',
    'Verschieben':'Move',
    'Link kopiert':'Link copied',
    'Teilen':'Share',
    'Album wird angelegt …':'Creating album …',
    'Album vollständig angelegt.':'Album created successfully.',
    'Bitte nur MP3-Dateien auswählen oder ablegen.':'Please select or drop MP3 files only.',
    'Albumdaten speichern':'Save album details',
    'Administratoren – Haben automatisch Zugriff':'Administrators – Automatically have access',
    'Administratoren - Haben automatisch Zugriff':'Administrators - Automatically have access',
    'z. B. Kunde / Veranstaltung':'e.g. client / event',
    'Backups und Rollback':'Backups and rollback',
    'Vor Updates und Wiederherstellungen wird automatisch gesichert.':'A backup is created automatically before updates and restores.',
    'Noch keine Backups vorhanden.':'No backups available yet.',
    'Update-Schutz':'Update protection',
    'Vor jeder Installation wird ein Backup der Programmdateien unter storage/backups/ angelegt.':'Before each installation, a backup of the application files is created in storage/backups/.',
    'config.php, uploads/ und storage/ werden durch Updates nicht überschrieben.':'config.php, uploads/ and storage/ are not overwritten by updates.',
    'Jetzt prüfen':'Check now',
    'Neue Versionen direkt aus den GitHub-Releases installieren.':'Install new versions directly from GitHub releases.',
    'Migration und vollständige Datensicherung':'Migration and full data backup',
    'Sichert Datenbank, Cover und Audiodateien für den Umzug auf eine andere installierte Music-Share-Instanz.':'Backs up the database, covers and audio files for migration to another installed Music Share instance.',
    'Backup von einer anderen Instanz hochladen':'Upload backup from another instance',
    'Noch keine Migrationsbackups vorhanden.':'No migration backups available yet.',
    'ZIP hochladen und prüfen':'Upload and validate ZIP',
    'Der Trash ist leer.':'The trash is empty.',
    'Der The trash is empty.':'The trash is empty.',
    'Papierkorb ist leer.':'The trash is empty.'
  }));

  const french = new Map(Object.entries({
    'Papierkorb':'Corbeille','Eigenes Album':'Mon album','Fremdes Album':'Album d’un autre utilisateur',
    'Albumtitel':'Titre de l’album','Erscheinungsjahr':'Année de sortie','Beschreibung':'Description',
    'Cover löschen':'Supprimer la pochette','Titel':'Piste','Titel hochladen':'Téléverser des pistes',
    'Neue Titel hochladen':'Téléverser de nouvelles pistes','Bereit':'Prêt','Fehler':'Erreur','Hinweis':'Information',
    'Bestätigen':'Confirmer','Abbrechen':'Annuler','Löschen':'Supprimer','Schließen':'Fermer','Speichern':'Enregistrer',
    'Wiederherstellen':'Restaurer','Endgültig löschen':'Supprimer définitivement','Alle Titel auswählen':'Sélectionner toutes les pistes',
    'Ausgewählte löschen':'Supprimer la sélection','CD hinzufügen':'Ajouter un disque','Albumtitel übernehmen?':'Utiliser le titre de l’album ?',
    'Titel ersetzen':'Remplacer le titre','Cover übernehmen?':'Utiliser la pochette ?','Cover übernehmen':'Utiliser la pochette',
    'Titel wirklich löschen?':'Supprimer cette piste ?','Titel löschen':'Supprimer les pistes','Verschieben':'Déplacer',
    'Link kopiert':'Lien copié','Teilen':'Partager','Album wird angelegt …':'Création de l’album …',
    'Album vollständig angelegt.':'Album créé avec succès.','Bitte nur MP3-Dateien auswählen oder ablegen.':'Veuillez sélectionner ou déposer uniquement des fichiers MP3.',
    'Der Trash ist leer.':'La corbeille est vide.','Der The trash is empty.':'La corbeille est vide.'
  }));

  const translateText = value => {
    const trimmed = value.trim();
    if (!trimmed) return value;
    const dictionary = isFrench ? french : exact;
    if (dictionary.has(trimmed)) return value.replace(trimmed, dictionary.get(trimmed));

    let out = value;
    const patterns = [
      [/^(\s*)Titel (\d+) von (\d+) wird hochgeladen …(\s*)$/, '$1Track $2 of $3 is uploading …$4'],
      [/^(\s*)CD (\d+) · Titel (\d+)(\s*)$/, '$1CD $2 · Track $3$4'],
      [/^(\s*)CD (\d+) · ohne TRACK(\s*)$/, '$1CD $2 · without TRACK$3'],
      [/^(\s*)MP3-Reihenfolge: CD (\d+), Titel (\d+)(\s*)$/, '$1MP3 order: CD $2, track $3$4'],
      [/^(\s*)CD (\d+) · keine TRACK-Nummer erkannt(\s*)$/, '$1CD $2 · no TRACK number detected$3'],
      [/^(\s*)(\d+) ausgewählte Titel wirklich dauerhaft löschen\?(\s*)$/, '$1Permanently delete $2 selected tracks?$3'],
      [/^(\s*)Upload abgeschlossen, (\d+) Datei\(en\) fehlgeschlagen\.(\s*)$/, '$1Upload complete, $2 file(s) failed.$3'],
      [/^(\s*)Läuft in (\d+) Tagen ab(\s*)$/, '$1Expires in $2 days$3'],
      [/^(\s*)(\d+) Titel(\s*)$/, '$1$2 tracks$3'],
    ];
    for (const [pattern, replacement] of patterns) out = out.replace(pattern, replacement);
    return out;
  };

  const processNode = node => {
    if (node.nodeType === Node.TEXT_NODE) {
      const translated = translateText(node.nodeValue);
      if (translated !== node.nodeValue) node.nodeValue = translated;
      return;
    }
    if (node.nodeType !== Node.ELEMENT_NODE) return;
    for (const attr of ['title','aria-label','placeholder']) {
      if (node.hasAttribute(attr)) {
        const current = node.getAttribute(attr);
        const translated = translateText(current);
        if (translated !== current) node.setAttribute(attr, translated);
      }
    }
    node.childNodes.forEach(processNode);
  };

  processNode(document.body);

  const enhanceFileInputs = root => {
    const scope = root?.querySelectorAll ? root : document;
    const inputs = [];
    if (root?.matches?.('input[type="file"]')) inputs.push(root);
    scope.querySelectorAll?.('input[type="file"]:not([data-i18n-file-ready])').forEach(input => inputs.push(input));
    inputs.forEach(input => {
      if (input.dataset.i18nFileReady) return;
      input.dataset.i18nFileReady = '1';

      const wrapper = document.createElement('div');
      wrapper.className = 'input-group i18n-file-input';
      const button = document.createElement('button');
      button.type = 'button';
      button.className = 'btn btn-outline-secondary';
      button.textContent = isFrench ? 'Choisir un fichier' : 'Choose file';
      const label = document.createElement('span');
      label.className = 'form-control text-body-secondary text-truncate';
      label.textContent = isFrench ? 'Aucun fichier sélectionné' : 'No file selected';

      input.parentNode.insertBefore(wrapper, input);
      wrapper.append(button, label);
      input.classList.add('visually-hidden');
      wrapper.append(input);

      button.addEventListener('click', () => input.click());
      input.addEventListener('change', () => {
        if (!input.files?.length) {
          label.textContent = isFrench ? 'Aucun fichier sélectionné' : 'No file selected';
        } else if (input.files.length === 1) {
          label.textContent = input.files[0].name;
        } else {
          label.textContent = isFrench ? `${input.files.length} fichiers sélectionnés` : `${input.files.length} files selected`;
        }
      });
    });
  };

  enhanceFileInputs(document);




  new MutationObserver(records => {
    records.forEach(record => record.addedNodes.forEach(node => { processNode(node); enhanceFileInputs(node); }));
  }).observe(document.body, {childList:true, subtree:true});
})();