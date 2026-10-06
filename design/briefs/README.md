# Briefs

One brief per surface: what a designer reads before drawing it. **Every file here except this one is generated**
by `tools/design_briefs.py`, from `../inventory/`, `../direction.md` and `../wireframes/manifest.json`. A brief
is never edited by hand, so it cannot say more than the inventory does. To change a brief, change the inventory
and regenerate.

```bash
python tools/design_briefs.py --write     # write the brief of every manifest entry that has one
python tools/design_briefs.py             # check that the committed briefs are current
python tools/design_briefs.py --surface sf-cart   # print any surface's brief without committing it
```

**Which briefs exist is decided by the manifest.** The 32 here are the surfaces of the calibration set. The rest
are generated batch by batch, when each review batch opens (`../wireframes/workflow.md`).

**A brief carries ids and structure, not the wording of the signed register.** The repository is public, and the
signed documents stay outside it (`../sources.json`). A designer reads the wording of each row in the signed
register. Working copies with the wording merged in can be produced on the developer's machine, outside the
repository only:

```bash
python tools/design_briefs.py --register "<path to the signed register>" --out "<a folder outside the repository>"
```

A brief existing does not mean a wireframe may be drawn. The calibration set starts on the owner's instruction.
