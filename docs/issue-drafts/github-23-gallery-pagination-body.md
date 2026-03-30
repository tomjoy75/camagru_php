# Issue #23 — texte à coller dans GitHub

Ouvre ce fichier dans l’éditeur, sélectionne **du `## Résumé` jusqu’à la fin du fichier** (sans cette ligne d’explication ni le `# Issue` ci-dessus), copie, colle dans le corps de l’issue.

---

## Résumé

Pagination de la galerie publique `GET /gallery` : ordre **du plus récent au plus ancien**, **page_size ≥ 5** (implémenté : 5), navigation via **`?page=`**, sans auth ni commentaires / likes. Aligné sur `docs/feature_tree.md` (module Gallery).

## Spec (source de vérité)

`docs/specs/gallery_pagination.md` — objectif, comportement, contraintes, critères de succès, **Implementation Plan**, **Tests** (dont blocs `curl`).

## Implémentation — checklist

- [ ] `ImageRepository::countForPublicGallery(): int`
- [ ] `ImageRepository::findPageForPublicGallery(int $limit, int $offset): array` (`ORDER BY created_at DESC`, LIMIT / OFFSET paramétrés)
- [ ] `GalleryController::show()` : taille de page (≥ 5), lecture / normalisation de `$_GET['page']`, calcul + clamp des pages, chargement de la page ; réutiliser le chemin d’erreur DB existant
- [ ] Passer `images`, `currentPage`, `totalPages`, `pageSize` à la vue
- [ ] `gallery.php` : conserver grille + états vide / erreur ; liens Previous / Next (ou équivalent) avec `?page=` si `totalPages > 1` ; échapper les `href`

## Tests — checklist

- [ ] **S1** `GET /gallery` → 200
- [ ] **S2** `GET /gallery?page=1` → 200
- [ ] **S3** Plus de `page_size` images (fichiers présents sur disque) : page 2 ≠ page 1, pas de doublons ; contrôles de pagination si `totalPages > 1`
- [ ] **F1** chemin inconnu (ex. `/gallerie`) → 404
- [ ] **E1** `page=0`, `-1`, `abc`, vide → 200, pas de 500
- [ ] **E2** `page=999999` → 200, pas de 500 (clamp selon plan)
- [ ] **E3** corpus ≤ `page_size` → 200, tout sur une page, UI pagination minimale ou absente

Commandes : section **Execute tests** du spec, `BASE=http://localhost:8080`.

## Notes

- Ne pas élargir le scope (pas de page détail, filtres, likes, commentaires).
- Gate §7.5 : ne pas merger sans validation contre le spec et la checklist test.
