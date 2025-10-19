# HTML and SVG Migration Plan

This document outlines the steps to convert HTML to Laravel Collective components and move SVGs to separate components.

## 1. Converting to Laravel Collective HTML

### 1.1 Form Elements

Replace Spatie HTML with Laravel Collective Form components. Here's a conversion guide:

#### Before (Spatie HTML):
```php
{!! \Spatie\Html\Html::text('name', 'John')->id('name')->class('form-control') !!}
```

#### After (Laravel Collective):
```php
{{ Form::text('name', 'John', ['id' => 'name', 'class' => 'form-control']) }}
```

### 1.2 Common Elements to Convert

1. **Text Inputs**:
   ```php
   {{ Form::text('field_name', $value, ['class' => 'form-control', 'placeholder' => 'Enter value']) }}
   ```

2. **Email Inputs**:
   ```php
   {{ Form::email('email', $value, ['class' => 'form-control', 'placeholder' => 'Enter email']) }}
   ```

3. **Password Inputs**:
   ```php
   {{ Form::password('password', ['class' => 'form-control', 'placeholder' => 'Enter password']) }}
   ```

4. **Textarea**:
   ```php
   {{ Form::textarea('description', $value, ['class' => 'form-control', 'rows' => 3]) }}
   ```

5. **Select Dropdowns**:
   ```php
   {{ Form::select('category', $categories, $selectedValue, ['class' => 'form-select', 'placeholder' => 'Select category']) }}
   ```

6. **Checkboxes**:
   ```php
   {{ Form::checkbox('remember', 1, $checked, ['class' => 'form-check-input']) }}
   ```

7. **Radio Buttons**:
   ```php
   {{ Form::radio('option', 'value', $checked, ['class' => 'form-check-input']) }}
   ```

8. **File Uploads**:
   ```php
   {{ Form::file('image', ['class' => 'form-control']) }}
   ```

9. **Hidden Fields**:
   ```php
   {{ Form::hidden('user_id', $userId) }}
   ```

10. **Submit Buttons**:
    ```php
    {{ Form::submit('Save', ['class' => 'btn btn-primary']) }}
    ```

### 1.3 Form Opening and Closing

```php
{{ Form::open(['route' => 'posts.store', 'method' => 'POST', 'files' => true]) }}
    <!-- Form fields here -->
{{ Form::close() }}
```

For model-bound forms:

```php
{{ Form::model($post, ['route' => ['posts.update', $post->id], 'method' => 'PUT', 'files' => true]) }}
    <!-- Form fields here -->
{{ Form::close() }}
```

## 2. SVG Components Strategy

### 2.1 Create SVG Components

1. Create a directory structure for SVG components:
   ```
   resources/views/components/icons/
   ```

2. Create individual Blade components for each SVG icon:

   For example: `resources/views/components/icons/user.blade.php`:
   ```php
   <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" {{ $attributes->merge(['class' => 'w-6 h-6']) }}>
       <path d="M12 2a5 5 0 1 0 5 5 5 5 0 0 0-5-5zm0 8a3 3 0 1 1 3-3 3 3 0 0 1-3 3zm9 11v-1a7 7 0 0 0-7-7h-4a7 7 0 0 0-7 7v1h2v-1a5 5 0 0 1 5-5h4a5 5 0 0 1 5 5v1z"/>
   </svg>
   ```

### 2.2 Usage of SVG Components

Instead of inline SVGs, use the component:

```php
<x-icons.user class="text-primary w-5 h-5" />
```

### 2.3 SVG Components List to Create

Based on common usage patterns in job portal applications, create these components:

1. User/Profile
2. Company/Building
3. Job/Briefcase
4. Search
5. Location/Map Marker
6. Email
7. Phone
8. Calendar
9. Clock
10. Money/Currency
11. Document/Resume
12. Edit/Pencil
13. Delete/Trash
14. View/Eye
15. Settings/Gear
16. Notification/Bell
17. Dashboard/Home
18. List/Menu
19. Arrow (up, down, left, right)
20. Check/Success
21. X/Close/Error
22. Social media icons (Facebook, Twitter, LinkedIn, etc.)

## 3. Implementation Approach

1. **Inventory Existing Usage**:
   - Identify all Spatie HTML form elements
   - Catalog all inline SVGs

2. **Conversion Priority**:
   - Start with high-traffic pages
   - Create the most frequently used SVG components first
   - Convert forms one at a time

3. **Testing Methodology**:
   - Test each converted form for proper functionality
   - Ensure SVG components render correctly
   - Verify responsive behavior on all device sizes

4. **Documentation**:
   - Create a component guide for developers
   - Document all available SVG icons

## 4. Benefits

- **Maintainability**: Centralized icon management
- **Consistency**: Uniform styling across the application
- **Performance**: Reduced code duplication
- **Accessibility**: Easier to implement a11y attributes
- **Flexibility**: SVG components can accept attributes for customization 