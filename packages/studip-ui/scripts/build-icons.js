import fs from 'fs'
import path from 'node:path'
import { fileURLToPath } from 'node:url'
import { optimize } from 'svgo'

const __filename = fileURLToPath(import.meta.url)
const __dirname = path.dirname(__filename)

const iconsDir = path.resolve(__dirname, '../../../public/assets/images/icons/black')
const distDir = path.resolve(__dirname, '../dist/assets/images/icons')
const outFile = path.resolve(distDir, 'icons.svg')

const studipDistDir = path.resolve(__dirname, '../../../public/assets/images/icons')
const studipOutFile = path.resolve(studipDistDir, 'icons.svg')

fs.mkdirSync(distDir, { recursive: true })

const files = fs.readdirSync(iconsDir).filter((f) => f.endsWith('.svg'))
console.log(`Gefundene SVG-Dateien: ${files.length}`)

const iconNames = files.map(f => path.basename(f, '.svg'))

const listFile = path.resolve(distDir, 'icons-list.js')
fs.writeFileSync(
    listFile,
    `export default ${JSON.stringify(iconNames)};`
)
console.log(`✅ Icon-Liste erzeugt: ${listFile} (${iconNames.length} Icons)`)

let spriteContent = `<svg xmlns="http://www.w3.org/2000/svg" style="display:none">`

files.forEach((file) => {
    const svg = fs.readFileSync(path.join(iconsDir, file), 'utf-8')

    const optimized = optimize(svg, {
        multipass: true,
        plugins: [
            'preset-default',
            'removeComments',
            'removeMetadata',
            'removeTitle',
            'removeDesc',
            'removeDimensions',
            'convertColors',
            'removeUselessDefs',
            'cleanupAttrs',
            'convertPathData',
            'removeEmptyContainers',
        ],
    }).data

    const viewBoxMatch = svg.match(/viewBox="([^"]+)"/)
    const viewBox = viewBoxMatch ? ` viewBox="${viewBoxMatch[1]}"` : ' viewBox="0 0 24 24"'

    const inner = optimized.replace(/<\s*svg[^>]*>/, '').replace(/<\/svg>/, '')
    const id = path.basename(file, '.svg')
    spriteContent += `<symbol id="icon-${id}" fill="currentColor"${viewBox}>${inner}</symbol>`
})

spriteContent += '</svg>'

fs.writeFileSync(outFile, spriteContent)
fs.writeFileSync(studipOutFile, spriteContent)

const stats = fs.statSync(outFile)
console.log(`✅ Sprite erzeugt: ${outFile} (${(stats.size / 1024).toFixed(1)} KB)`)
