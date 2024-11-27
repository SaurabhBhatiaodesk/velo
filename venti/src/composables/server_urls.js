export const serverRoot = (process.env.SERVER_ROOT.endsWith('/')) ? process.env.SERVER_ROOT : process.env.SERVER_ROOT + '/'
export function serverLink (path) {
  let link = (process.env.SERVER_ROOT.endsWith('/')) ? process.env.SERVER_ROOT.slice(0, -1) : process.env.SERVER_ROOT
  if (!path.startsWith('/')) {
    link += '/'
  }
  link += path
  return link
}
