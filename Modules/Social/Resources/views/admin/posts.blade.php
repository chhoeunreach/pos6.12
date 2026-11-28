@extends('social::layouts.master')
@section('page_title', 'Social Platform — Feed & Sample Posts')
@section('module_content')
<div class="row">
    <div class="col-md-3 col-sm-6">
        <div class="info-box">
            <span class="info-box-icon bg-aqua"><i class="fa fa-newspaper-o"></i></span>
            <div class="info-box-content">
                <span class="info-box-text">Total Posts</span>
                <span class="info-box-number">{{ $stats['total_posts'] }}</span>
                <small>{{ $stats['published_posts'] }} published</small>
            </div>
        </div>
    </div>
    <div class="col-md-3 col-sm-6">
        <div class="info-box">
            <span class="info-box-icon bg-green"><i class="fa fa-thumbs-up"></i></span>
            <div class="info-box-content">
                <span class="info-box-text">Reactions & Comments</span>
                <span class="info-box-number">{{ $stats['total_reactions'] }} / {{ $stats['total_comments'] }}</span>
            </div>
        </div>
    </div>
    <div class="col-md-3 col-sm-6">
        <div class="info-box">
            <span class="info-box-icon bg-yellow"><i class="fa fa-flag"></i></span>
            <div class="info-box-content">
                <span class="info-box-text">Open Reports</span>
                <span class="info-box-number">{{ $stats['open_reports'] }}</span>
            </div>
        </div>
    </div>
    <div class="col-md-3 col-sm-6">
        <div class="info-box">
            <span class="info-box-icon bg-{{ !empty($settings['api_enabled']) ? 'blue' : 'red' }}"><i class="fa fa-plug"></i></span>
            <div class="info-box-content">
                <span class="info-box-text">API Status ({{ strtoupper($settings['mode'] ?? 'sample') }})</span>
                <span class="info-box-number">{{ !empty($settings['api_enabled']) ? 'ON' : 'OFF' }}</span>
            </div>
        </div>
    </div>
</div>

<div class="box box-primary">
    <div class="box-header with-border">
        <h3 class="box-title">Create New Post (or Sample Post)</h3>
        <div class="box-tools pull-right">
            <form method="post" action="{{ route('social.admin.posts.seed_sample') }}" style="display:inline;">
                @csrf
                <button type="submit" class="btn btn-sm btn-warning">
                    <i class="fa fa-magic"></i> Seed 3 Sample Posts
                </button>
            </form>
        </div>
    </div>
    <div class="box-body">
        <form method="post" action="{{ route('social.admin.posts.store') }}" enctype="multipart/form-data">
            @csrf
            <div class="row">
                <div class="col-md-5 form-group">
                    <label>Title *</label>
                    <input type="text" name="title" class="form-control" required placeholder="e.g. iPhone 16 Pro Max — Installment Promotion">
                </div>
                <div class="col-md-3 form-group">
                    <label>Post Type</label>
                    <select name="post_type" class="form-control">
                        <option value="product_promo">Product Promotion</option>
                        <option value="photo">Photo</option>
                        <option value="video">Video</option>
                        <option value="announcement">Announcement</option>
                        <option value="tips">News / Tips</option>
                    </select>
                </div>
                <div class="col-md-2 form-group">
                    <label>Price</label>
                    <input type="number" step="0.01" name="price" class="form-control" placeholder="1199">
                </div>
                <div class="col-md-2 form-group">
                    <label>Monthly Est.</label>
                    <input type="number" step="0.01" name="estimated_monthly_payment" class="form-control" placeholder="58">
                </div>
            </div>
            <div class="row">
                <div class="col-md-6 form-group">
                    <label>Content</label>
                    <textarea name="content" rows="2" class="form-control" placeholder="Post details, installment terms, shop contact..."></textarea>
                </div>
                <div class="col-md-3 form-group">
                    <label>Image / Video File</label>
                    <input type="file" name="file" class="form-control">
                </div>
                <div class="col-md-3 form-group">
                    <label>Status & Pin</label>
                    <div class="input-group">
                        <select name="status" class="form-control">
                            <option value="published">Published</option>
                            <option value="draft">Draft</option>
                        </select>
                    </div>
                    <label style="margin-top:6px;">
                        <input type="checkbox" name="is_pinned" value="1"> Pin to top of feed
                    </label>
                </div>
            </div>
            <button type="submit" class="btn btn-primary"><i class="fa fa-paper-plane"></i> Publish to Social Feed</button>
        </form>
    </div>
</div>

<div class="box box-default">
    <div class="box-header with-border">
        <h3 class="box-title">Posts in <code>social.social_posts</code></h3>
    </div>
    <div class="box-body table-responsive">
        <table class="table table-bordered table-striped">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Title</th>
                    <th>Type</th>
                    <th>Price / Monthly</th>
                    <th>Engagement</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($posts as $post)
                    <tr>
                        <td>#{{ $post->id }}</td>
                        <td>
                            <strong>{{ $post->title }}</strong>
                            @if($post->is_pinned)
                                <span class="label label-info">Pinned</span>
                            @endif
                            <br>
                            <small class="text-muted">{{ \Illuminate\Support\Str::limit($post->content, 90) }}</small>
                        </td>
                        <td><span class="label label-default">{{ $post->post_type }}</span></td>
                        <td>
                            @if($post->price)
                                {{ $post->currency }} {{ number_format($post->price, 2) }}
                            @endif
                            @if($post->estimated_monthly_payment)
                                <br><small class="text-success">~{{ $post->currency }} {{ number_format($post->estimated_monthly_payment, 2) }}/mo</small>
                            @endif
                        </td>
                        <td>
                            <small>
                                <i class="fa fa-thumbs-up"></i> {{ $post->likes_count }} &nbsp;
                                <i class="fa fa-comment"></i> {{ $post->comments_count }} &nbsp;
                                <i class="fa fa-share"></i> {{ $post->shares_count }} &nbsp;
                                <i class="fa fa-eye"></i> {{ $post->views_count }}
                            </small>
                        </td>
                        <td>
                            <span class="label label-{{ $post->status === 'published' ? 'success' : ($post->status === 'flagged' ? 'danger' : 'warning') }}">
                                {{ strtoupper($post->status) }}
                            </span>
                        </td>
                        <td style="white-space:nowrap;">
                            <form method="post" action="{{ route('social.admin.posts.pin', $post->id) }}" style="display:inline;">
                                @csrf
                                <button class="btn btn-xs btn-default" title="Toggle Pin">
                                    <i class="fa fa-thumb-tack"></i>
                                </button>
                            </form>
                            <form method="post" action="{{ route('social.admin.posts.status', $post->id) }}" style="display:inline;">
                                @csrf
                                <button class="btn btn-xs btn-info" title="Toggle Publish/Draft">
                                    {{ $post->status === 'published' ? 'Draft' : 'Publish' }}
                                </button>
                            </form>
                            <form method="post" action="{{ route('social.admin.posts.destroy', $post->id) }}" style="display:inline;" onsubmit="return confirm('Delete this post?');">
                                @csrf
                                @method('DELETE')
                                <button class="btn btn-xs btn-danger"><i class="fa fa-trash"></i></button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="text-center text-muted" style="padding:24px;">
                            No posts yet in <code>social.social_posts</code>. Click <strong>Seed 3 Sample Posts</strong> above or create your first post!
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
