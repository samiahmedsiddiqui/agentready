<?php
/**
 * Tests for the HTML to Markdown pipeline (extractor and converter).
 *
 * @package AgentReady
 */

declare(strict_types=1);

namespace AgentReady\Tests\Unit;

use AgentReady\Rendering\Converter;
use AgentReady\Rendering\Extractor;
use AgentReady\Rendering\Selector;
use PHPUnit\Framework\TestCase;

final class ConverterTest extends TestCase {

	private function markdown( string $html, string $selector = 'body' ): string {
		$node = ( new Extractor( new Selector() ) )->extract( '<html><body>' . $html . '</body></html>', $selector, 'https://example.com/page/' );

		return null === $node ? '' : ( new Converter() )->convert( $node );
	}

	/**
	 * @dataProvider cases
	 */
	public function test_converts( string $html, string $expected ): void {
		$this->assertSame( $expected, $this->markdown( $html ) );
	}

	/**
	 * @return array<string, array{string, string}>
	 */
	public function cases(): array {
		return array(
			'heading and inline'    => array( '<h1>Title</h1><p>Hello <strong>bold</strong> and <em>it</em>.</p>', "# Title\n\nHello **bold** and *it*.\n" ),
			'spaces move outside'   => array( '<p>a<b> b </b>c</p>', "a **b** c\n" ),
			'link made absolute'    => array( '<p><a href="/about/">About</a></p>', "[About](https://example.com/about/)\n" ),
			'relative link'         => array( '<p><a href="../x">X</a></p>', "[X](https://example.com/x)\n" ),
			'unsafe link dropped'   => array( '<p><a href="javascript:alert(1)">Click</a></p>', "Click\n" ),
			'mailto kept'           => array( '<p><a href="mailto:a@b.co">Mail</a></p>', "[Mail](mailto:a@b.co)\n" ),
			'unordered nested list' => array( '<ul><li>One</li><li>Two<ul><li>Nested</li></ul></li></ul>', "- One\n- Two\n  - Nested\n" ),
			'ordered list'          => array( '<ol><li>a</li><li>b</li></ol>', "1. a\n2. b\n" ),
			'table'                 => array( '<table><thead><tr><th>A</th><th>B</th></tr></thead><tbody><tr><td>1</td><td>2|3</td></tr></tbody></table>', "| A | B |\n| --- | --- |\n| 1 | 2\\|3 |\n" ),
			'code block'            => array( '<pre><code class="language-php">echo 1;</code></pre>', "```php\necho 1;\n```\n" ),
			'enlighter language'    => array( '<pre data-enlighter-language="css">a{}</pre>', "```css\na{}\n```\n" ),
			'inline code'           => array( '<p>Use <code>wp_head()</code></p>', "Use `wp_head()`\n" ),
			'image'                 => array( '<img src="/a.png" alt="Logo">', "![Logo](https://example.com/a.png)\n" ),
			'lazy image'            => array( '<img src="data:image/gif;base64,xx" data-src="/b.jpg" alt="B">', "![B](https://example.com/b.jpg)\n" ),
			'tracking pixel'        => array( '<p>x</p><img src="/p.gif" width="1" height="1">', "x\n" ),
			'blockquote'            => array( '<blockquote><p>Quote</p></blockquote>', "> Quote\n" ),
			'hard break'            => array( '<p>a<br>b</p>', "a  \nb\n" ),
			'escapes'               => array( '<p>2 * 3 [x] _y_ snake_case</p>', "2 \\* 3 \\[x\\] \\_y\\_ snake_case\n" ),
			'literal angle bracket' => array( '<p>&lt;script&gt;x&lt;/script&gt;</p>', "\\<script>x\\</script>\n" ),
			'iframe becomes link'   => array( '<iframe src="https://youtube.com/embed/x" title="Video"></iframe>', "[Video](https://youtube.com/embed/x)\n" ),
			'nested containers'     => array( '<div><div>One</div><div>Two</div></div>', "One\n\nTwo\n" ),
			'script and style gone' => array( '<p>Keep</p><script>alert(1)</script><style>p{}</style>', "Keep\n" ),
			'hidden gone'           => array( '<p aria-hidden="true">no</p><p>yes</p>', "yes\n" ),
			'forms and nav gone'    => array( '<nav>Menu</nav><form><input></form><p>Body</p>', "Body\n" ),
			'comment gone'          => array( '<p>a</p><!-- secret --><p>b</p>', "a\n\nb\n" ),
			'definition list'       => array( '<dl><dt>Term</dt><dd>Meaning</dd></dl>', "**Term**\n\nMeaning\n" ),
			'colspan'               => array( '<table><tr><th colspan="2">H</th></tr><tr><td>a</td><td>b</td></tr></table>', "| H |  |\n| --- | --- |\n| a | b |\n" ),
			'heading-like text'     => array( '<p># not a heading</p>', "\\# not a heading\n" ),
			'site header and footer' => array( '<header class="site-header"><p>Brand</p></header><p>Body</p><footer><p>Copyright</p></footer>', "Body\n" ),
			'wrapped site header'   => array( '<div id="page"><header><p>Brand</p></header><p>Body</p></div>', "Body\n" ),
			'article header kept'   => array( '<article><header><h1>Title</h1></header><p>Text</p><footer><p>By Ann</p></footer></article>', "# Title\n\nText\n\nBy Ann\n" ),
			'logo repeats link text' => array( '<a href="/c/">Claude <img src="/c.svg" alt="Claude"></a>', "[Claude](https://example.com/c/)\n" ),
			'logo in nested wrappers' => array( '<a href="/c/"><div class="top"><div class="name">Claude</div><div class="image"><img src="/c.svg" alt="Claude"></div></div></a>', "[Claude](https://example.com/c/)\n" ),
			'image-only link kept'  => array( '<a href="/x/"><img src="/x.svg" alt="Logo"></a>', "[![Logo](https://example.com/x.svg)](https://example.com/x/)\n" ),
			'label then logo'       => array( '<div>AgentCore</div><div><img src="/a.svg" alt="AgentCore"></div>', "AgentCore\n" ),
			'logo then caption'     => array( '<div><img src="/d.svg" alt="Diagram one"></div><p>Diagram one</p>', "Diagram one\n" ),
			'distinct alt kept'     => array( '<div>Overview</div><div><img src="/a.svg" alt="Architecture"></div>', "Overview\n\n![Architecture](https://example.com/a.svg)\n" ),
			'icon joins text'       => array( '<p>✓</p><p>Connect any MCP client</p>', "✓ Connect any MCP client\n" ),
			'cross joins text'      => array( '<div>✗</div><div>Pro only</div>', "✗ Pro only\n" ),
			'icon before list'      => array( '<p>✓</p><ul><li>a</li></ul>', "✓\n\n- a\n" ),
			'superscript footnote'  => array( '<p>Launch the lab<sup>1</sup></p>', "Launch the lab^1\n" ),
			'superscript symbol'    => array( '<p>Acme<sup>®</sup></p>', "Acme®\n" ),
			'long superscript'      => array( '<p>x<sup>note here</sup></p>', "x (note here)\n" ),
			'bold heading unwrapped' => array( '<h2><strong>Every agent</strong></h2>', "## Every agent\n" ),
			'nested strong in a paragraph' => array( '<p><strong><strong>x</strong></strong></p>', "**x**\n" ),
			'nested emphasis'       => array( '<p><em><em>x</em></em></p>', "*x*\n" ),
			'bold italic kept'      => array( '<p><em><strong>x</strong></em></p>', "***x***\n" ),
			'nested strong heading' => array( '<h2><strong><strong>Connecting Acme in AgentCore</strong></strong></h2>', "## Connecting Acme in AgentCore\n" ),
			'heading with bold link text' => array( '<h3><a href="/x/"><strong>Docs</strong></a></h3>', "### [**Docs**](https://example.com/x/)\n" ),
			'partial emphasis kept' => array( '<h2>The <em>Agentic</em> way</h2>', "## The *Agentic* way\n" ),
			'code fence untouched'  => array( "<p>a</p><pre><code>x\n\n\ny</code></pre>", "a\n\n```\nx\n\n\ny\n```\n" ),
			'banner role gone'      => array( '<div role="banner"><p>Logo</p></div><p>Body</p>', "Body\n" ),
			'contentinfo role gone' => array( '<p>Body</p><div role="contentinfo"><p>Legal</p></div>', "Body\n" ),
		);
	}

	public function test_prefers_first_selector_with_content(): void {
		$html = '<div id="content"><p>Inner</p></div><main><p>Main</p></main>';
		$node = ( new Extractor( new Selector() ) )->extract( '<body>' . $html . '</body>', 'main, #content', 'https://example.com/' );

		$this->assertSame( "Main\n", ( new Converter() )->convert( $node ) );
	}

	public function test_skips_empty_match_and_falls_through(): void {
		$html = '<main></main><div id="content"><p>Inner</p></div>';

		$this->assertSame( "Inner\n", $this->markdown( $html, 'main, #content' ) );
	}

	public function test_falls_back_to_body_when_nothing_matches(): void {
		$this->assertSame( "Hi\n", $this->markdown( '<p>Hi</p>', 'main' ) );
	}

	public function test_empty_html_returns_null(): void {
		$this->assertNull( ( new Extractor( new Selector() ) )->extract( '  ', 'main', 'https://example.com/' ) );
	}

	public function test_keeps_header_and_footer_inside_the_selected_main(): void {
		$html = '<header><p>Site</p></header><main><header><h1>T</h1></header><p>x</p><footer><p>f</p></footer></main>';

		$this->assertSame( "# T\n\nx\n\nf\n", $this->markdown( $html, 'main' ) );
	}

	public function test_extra_selectors_are_added_to_the_built_in_list(): void {
		$extractor = ( new Extractor( new Selector() ) )->with_extra( array( '.cookie', 'article footer' ) );
		$html      = '<body><div class="cookie">Accept</div><article><p>Read</p><footer><p>By Ann</p></footer></article><script>x</script></body>';
		$node      = $extractor->extract( $html, 'article', 'https://example.com/' );

		$this->assertSame( "Read\n", ( new Converter() )->convert( $node ) );
	}

	public function test_custom_strip_selectors(): void {
		$extractor = new Extractor( new Selector(), array( '.ads' ) );
		$node      = $extractor->extract( '<body><div class="ads">Buy</div><p>Read</p></body>', 'body', 'https://example.com/' );

		$this->assertSame( "Read\n", ( new Converter() )->convert( $node ) );
	}
}
