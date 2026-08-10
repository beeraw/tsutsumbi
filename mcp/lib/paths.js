import { realpathSync } from 'node:fs';
import { isAbsolute, resolve } from 'node:path';

/**
 * Brings a working directory to the form tsutsumbi identifies a project by.
 *
 * Resolving symlinks happens here rather than server-side, and that placement is
 * the point: this process runs next to the files, the server does not
 * necessarily. A path that resolved one way today and another way tomorrow would
 * quietly create a second project for the same directory, which is exactly what
 * resolve_project must never do — so the resolution is done once, where the
 * filesystem actually is, and the resolved path is what travels.
 *
 * A path that does not exist locally is passed through after lexical
 * normalisation. Being unable to stat a directory is not a reason to refuse to
 * record work against it.
 */
export function resolveProjectPath(rawPath) {
  if (typeof rawPath !== 'string' || rawPath.trim() === '') {
    throw new Error('A project path is required.');
  }

  const trimmed = rawPath.trim();

  if (!isAbsolute(trimmed)) {
    throw new Error(`The project path must be absolute, got "${trimmed}".`);
  }

  const lexical = stripTrailingSlash(resolve(trimmed));

  try {
    return stripTrailingSlash(realpathSync(lexical));
  } catch {
    return lexical;
  }
}

function stripTrailingSlash(path) {
  return path.length > 1 && path.endsWith('/') ? path.slice(0, -1) : path;
}
