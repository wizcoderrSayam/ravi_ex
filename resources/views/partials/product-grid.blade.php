@foreach ($products as $product)
  <div @class(['col', 'd-none' => $hidden ?? false]) data-aos="fade-up" data-aos-delay="{{ $loop->index % 4 * 100 }}">
    <x-product-card :product="$product" />
  </div>
@endforeach
