export function normalizeToken(value) {
  return String(value ?? '').trim().replace(/\s+/g, ' ')
}

export function splitCompoundValue(value) {
  const normalized = normalizeToken(value)
  if (!normalized) return []
  if (normalized.includes(' ')) return [normalized]
  return normalized.split('/').map(normalizeToken).filter(Boolean)
}

export function isRoomLikeToken(value) {
  const token = normalizeToken(value).replace(/\s+/g, '')
  if (!token) return false
  if (/^\d{1,4}$/.test(token)) return true
  return /^(?:K\d{1,4}|T\d{1,2}|M\d{1,3}|KT)$/i.test(token)
}

export function isValidClassCode(value) {
  const token = normalizeToken(value)
  if (!token || isRoomLikeToken(token)) return false
  if (!/\p{L}/u.test(token)) return false
  return /^[\p{L}\p{N} ._\/-]+$/u.test(token)
}

export function isRoomToken(value) {
  return !isValidClassCode(value)
}

export function uniqueNormalized(values) {
  const unique = new Map()
  for (const value of values) {
    const normalized = normalizeToken(value)
    if (!normalized) continue
    unique.set(normalized.toLowerCase(), normalized)
  }
  return [...unique.values()]
}

export function sortClassCodes(left, right) {
  const a = normalizeToken(left)
  const b = normalizeToken(right)
  const am = a.match(/^(\d+)\.(.+)$/u)
  const bm = b.match(/^(\d+)\.(.+)$/u)

  if (am && bm) {
    const gradeDiff = Number(am[1]) - Number(bm[1])
    if (gradeDiff !== 0) return gradeDiff
    return am[2].localeCompare(bm[2], 'hu', { numeric: true, sensitivity: 'base' })
  }

  if (am && !bm) return -1
  if (!am && bm) return 1
  return a.localeCompare(b, 'hu', { numeric: true, sensitivity: 'base' })
}
