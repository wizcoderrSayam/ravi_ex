<?php

namespace Tests\Feature;

use Carepeerinfotech\Ecommerce\Models\Category;
use Database\Seeders\StorefrontCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StorefrontHomeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(StorefrontCatalogSeeder::class);
    }

    public function test_shop_sections_come_before_new_arrivals_and_bestsellers(): void
    {
        $response = $this->get(route('shop.home'));

        $response->assertOk();
        $response->assertSeeInOrder([
            'Shop for Girls', 'Shop for Boys', 'Shop for Men', 'Shop for Women',
            'New arrivals', 'Bestsellers',
        ]);
    }

    public function test_each_subcategory_tile_opens_its_filtered_listing(): void
    {
        $tiles = $this->homeTiles();

        $this->assertCount(14, $tiles);
        $this->assertSame(
            ['Lehenga', 'Sharara / Palazzo', 'Patiala Salwar Suits / Farshi Salwar', 'Frocks & Gowns'],
            array_column(array_slice($tiles, 0, 4), 'name'),
        );
        $this->assertSame('Lehenga', $tiles[route('shop.girls').'#lehenga']['name']);
        $this->assertSame('Coat Suits / Tuxedos', $tiles[route('shop.boys').'#coat-suits-tuxedos']['name']);
        $this->assertSame('Waistcoat Sets', $tiles[route('shop.men').'#waistcoat-sets']['name']);
        $this->assertSame('Co-ord Sets', $tiles[route('shop.women').'#co-ord-sets']['name']);
        $this->assertSame('/assets/images/categories/men-indo-westerns.webp', $tiles[route('shop.men').'#indo-westerns']['image']);
        $this->assertSame('/assets/images/categories/boys-jodhpuri.webp', $tiles[route('shop.boys').'#jodhpuri']['image']);
        $this->assertSame('/assets/images/categories/men-kurta-pajamas.webp', $tiles[route('shop.men').'#kurta-pajamas']['image']);
        $this->assertSame('/assets/images/categories/men-waistcoat-sets.webp', $tiles[route('shop.men').'#waistcoat-sets']['image']);
    }

    public function test_inactive_subcategories_are_left_off_the_home_page(): void
    {
        Category::firstWhere('slug', 'boys-jodhpuri')->update(['is_active' => false]);

        $tiles = $this->homeTiles();

        $this->assertCount(13, $tiles);
        $this->assertArrayNotHasKey(route('shop.boys').'#jodhpuri', $tiles);
    }

    public function test_kids_sections_are_category_carousels_and_men_sit_in_one_row(): void
    {
        $response = $this->get(route('shop.home'));
        $html = $response->getContent();

        $this->assertSame(2, substr_count($html, 'data-carousel'), 'girls and boys each get a carousel');
        $this->assertSame(2, substr_count($html, 'class="shop-section"'), 'men and women keep the band layout');

        $response->assertSeeInOrder([
            'Shop for Girls', 'style="--cards: 4"', 'Lehenga', 'data-rail-dots',
            'Shop for Boys', 'style="--cards: 6"', 'Sherwani', 'data-rail-dots',
            'Shop for Men', 'class="shop-grid cols-3 is-level"', 'Kurta Pajamas',
        ], false);
    }

    public function test_instagram_section_plays_the_store_reels_and_links_to_the_profile(): void
    {
        $html = $this->get(route('shop.home'))->assertOk()->getContent();

        preg_match_all('/<a class="reel" href="([^"]+)".*?<\/a>/s', $html, $reels, PREG_SET_ORDER);

        // Ten reels, listed twice so the row can glide endlessly; only the first set is announced or focusable.
        $this->assertCount(20, $reels);
        $this->assertSame(20, substr_count($html, 'data-reel'));
        $this->assertSame(10, substr_count($html, 'data-glide-copy'));
        $this->assertSame(10, substr_count($html, 'aria-hidden="true" tabindex="-1" data-glide-copy'));
        $this->assertStringContainsString('data-glide', $html);

        // Each tile is read on its own, so a tile missing its poster or video cannot borrow the next one's.
        foreach ($reels as [$tile, $url]) {
            $this->assertSame('https://www.instagram.com/ravi.exclusif/', $url);
            $this->assertSame(1, preg_match('/poster="([^"]+)"/', $tile, $poster), 'every reel has a poster');
            $this->assertSame(1, preg_match('/<source src="([^"]+)" type="video\/mp4">/', $tile, $video), 'every reel has a clip');
            $this->assertFileExists(public_path(parse_url($poster[1], PHP_URL_PATH)));
            $this->assertFileExists(public_path(parse_url($video[1], PHP_URL_PATH)));
        }

        $this->assertStringContainsString('<a class="btn btn-red" href="https://www.instagram.com/ravi.exclusif/" target="_blank" rel="noopener" data-aos="fade-up">Follow @ravi.exclusif</a>', $html);
        $this->assertStringNotContainsString('instagram.com/ravi.exclusive', $html, 'the footer links to the right handle');
        $this->assertStringContainsString('<a href="https://www.instagram.com/ravi.exclusif/" target="_blank" rel="noopener">Instagram</a>', $html);
    }

    public function test_occasions_are_plain_text_and_old_copy_is_gone(): void
    {
        $response = $this->get(route('shop.home'));

        $response->assertSee('Outfits for every occasion!');
        $response->assertSeeInOrder(['<div class="vy">', '<h3>Jaggo &amp; Dhol</h3>', 'Patiala suits · Kurta pajamas', '</div>'], false);
        $response->assertDontSee('<a class="vy"', false);
        $response->assertSee('Dressing Generations Since 1961');
        $response->assertDontSee('For the whole parivaar');
        $response->assertDontSee('Shop Punjabi Suits');
    }

    public function test_listing_page_has_a_filter_chip_for_each_tile_link(): void
    {
        $this->get(route('shop.girls'))
            ->assertSee('<a class="chip" href="#lehenga" data-sub-link="lehenga" data-sub-name="Lehenga">', false);
    }

    /**
     * The home page's category tiles, keyed by link (labels repeat across sections), in page order.
     *
     * @return array<string, array{name: string, image: ?string}>
     */
    private function homeTiles(): array
    {
        $html = $this->get(route('shop.home'))->assertOk()->getContent();

        // Carousel cards (.cat) label with an h3; band tiles (.shop-tile) with a name span.
        preg_match_all('/<a\s+class="(?:shop-tile|cat)"\s+href="([^"]+)".*?<\/a>/s', $html, $matches, PREG_SET_ORDER);

        $tiles = [];

        foreach ($matches as [$markup, $url]) {
            preg_match('/<(?:span class="shop-tile-name"|h3)>(.*?)<\/(?:span|h3)>/s', $markup, $label);
            preg_match('/<img\s+(?:class="art"\s+)?src="([^"]+)"/', $markup, $image);

            $tiles[$url] = ['name' => html_entity_decode(trim($label[1])), 'image' => $image[1] ?? null];
        }

        return $tiles;
    }
}
