/**
 * Builds docs/admin-studio-documentation.pdf from docs/documentation.html.
 *
 * Usage: node docs/build-pdf.js
 * Needs Playwright with Chromium (npm install -g playwright).
 */
const path = require( 'path' );
const { execSync } = require( 'child_process' );

function loadPlaywright() {
	try {
		return require( 'playwright' );
	} catch ( e ) {
		const root = execSync( 'npm root -g' ).toString().trim();
		return require( path.join( root, 'playwright' ) );
	}
}

( async () => {
	const { chromium } = loadPlaywright();
	const source = path.join( __dirname, 'documentation.html' );
	const output = path.join( __dirname, 'admin-studio-documentation.pdf' );

	const browser = await chromium.launch();
	const page = await browser.newPage();

	await page.goto( 'file://' + source, { waitUntil: 'networkidle' } );
	await page.pdf( {
		path: output,
		format: 'A4',
		printBackground: true,
		preferCSSPageSize: true,
		displayHeaderFooter: true,
		headerTemplate: '<span></span>',
		footerTemplate:
			'<div style="width:100%;font-size:8px;color:#6b7280;padding:0 18mm;display:flex;justify-content:space-between;font-family:-apple-system,Segoe UI,Roboto,Arial,sans-serif;">' +
			'<span>Admin Studio documentation</span>' +
			'<span><span class="pageNumber"></span> / <span class="totalPages"></span></span>' +
			'</div>',
	} );

	await browser.close();
	console.log( 'Wrote ' + output );
} )();
