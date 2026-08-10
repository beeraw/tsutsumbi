import assert from 'node:assert/strict';
import { mkdtempSync, mkdirSync, symlinkSync, realpathSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { describe, it } from 'node:test';

import { resolveProjectPath } from '../lib/paths.js';

describe('resolveProjectPath', () => {
  it('refuses a relative path', () => {
    assert.throws(() => resolveProjectPath('www/maji'), /must be absolute/);
  });

  it('refuses an empty path', () => {
    assert.throws(() => resolveProjectPath('   '), /required/);
  });

  it('drops a trailing slash', () => {
    assert.equal(resolveProjectPath('/home/dev/projects/maji/'), '/home/dev/projects/maji');
  });

  it('collapses the segments that mean nothing', () => {
    assert.equal(resolveProjectPath('/home/dev/projects/harusi/../maji/.'), '/home/dev/projects/maji');
  });

  it('passes through a path that does not exist locally', () => {
    assert.equal(resolveProjectPath('/nowhere/at/all'), '/nowhere/at/all');
  });

  /**
   * The reason this happens here and not on the server: two names for one
   * directory must reach resolve_project as the same path, or the same project
   * gets created twice.
   */
  it('follows a symlink to the directory it really is', () => {
    const root = realpathSync(mkdtempSync(join(tmpdir(), 'tsutsumbi-')));
    const real = join(root, 'maji');
    const link = join(root, 'shortcut');

    mkdirSync(real);
    symlinkSync(real, link);

    assert.equal(resolveProjectPath(link), real);
    assert.equal(resolveProjectPath(`${link}/`), resolveProjectPath(real));
  });

  it('is idempotent', () => {
    const once = resolveProjectPath('/home/dev/projects//harusi/../maji/');

    assert.equal(resolveProjectPath(once), once);
  });
});
